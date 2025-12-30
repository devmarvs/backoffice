<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations;

use App\Domain\Repository\CalendarEventRepositoryInterface;
use App\Domain\Repository\IntegrationRepositoryInterface;
use DateTimeImmutable;
use Google\Client;
use Google\Service\Calendar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class GoogleCalendarService
{
    public function __construct(
        private IntegrationRepositoryInterface $integrations,
        private CalendarEventRepositoryInterface $events,
        #[Autowire('%app.google_client_id%')] private string $clientId,
        #[Autowire('%app.google_client_secret%')] private string $clientSecret,
        #[Autowire('%app.google_redirect_url%')] private string $redirectUrl,
        #[Autowire('%app.google_scopes%')] private string $scopes,
        #[Autowire('%app.google_calendar_id%')] private string $calendarId
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->redirectUrl !== '';
    }

    public function getAuthUrl(string $state): string
    {
        $client = $this->buildClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function handleCallback(int $userId, string $code): array
    {
        $client = $this->buildClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException($token['error_description'] ?? 'Google auth failed.');
        }

        $refreshToken = $token['refresh_token'] ?? null;
        $expiresAt = null;
        if (isset($token['expires_in'])) {
            $expiresAt = (new DateTimeImmutable())->modify(sprintf('+%d seconds', (int) $token['expires_in']))
                ->format('Y-m-d H:i:sP');
        }

        return $this->integrations->upsert($userId, 'google_calendar', [
            'access_token' => $token['access_token'],
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
            'metadata' => ['calendar_id' => $this->calendarId],
        ]);
    }

    public function syncEvents(int $userId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): int
    {
        $integration = $this->integrations->findByUserAndProvider($userId, 'google_calendar');
        if ($integration === null) {
            throw new \RuntimeException('Google Calendar is not connected.');
        }

        $client = $this->buildClient();
        $client->setAccessToken([
            'access_token' => $integration['access_token'],
            'refresh_token' => $integration['refresh_token'],
            'expires_at' => isset($integration['expires_at']) ? strtotime((string) $integration['expires_at']) : null,
        ]);

        if ($client->isAccessTokenExpired() && !empty($integration['refresh_token'])) {
            $token = $client->fetchAccessTokenWithRefreshToken($integration['refresh_token']);
            if (!isset($token['error']) && isset($token['access_token'])) {
                $expiresAt = null;
                if (isset($token['expires_in'])) {
                    $expiresAt = (new DateTimeImmutable())->modify(sprintf('+%d seconds', (int) $token['expires_in']))
                        ->format('Y-m-d H:i:sP');
                }

                $this->integrations->upsert($userId, 'google_calendar', [
                    'access_token' => $token['access_token'],
                    'refresh_token' => $integration['refresh_token'],
                    'expires_at' => $expiresAt,
                    'metadata' => ['calendar_id' => $this->calendarId],
                ]);
            }
        }

        $service = new Calendar($client);
        $from = $from ?? new DateTimeImmutable('-7 days');
        $to = $to ?? new DateTimeImmutable('+30 days');

        $events = $service->events->listEvents($this->calendarId, [
            'singleEvents' => true,
            'orderBy' => 'startTime',
            'timeMin' => $from->format(DateTimeImmutable::ATOM),
            'timeMax' => $to->format(DateTimeImmutable::ATOM),
        ]);

        $payload = [];
        foreach ($events->getItems() as $event) {
            $start = $event->getStart();
            $end = $event->getEnd();
            $startAt = $start->getDateTime() ?? $start->getDate();
            $endAt = $end->getDateTime() ?? $end->getDate();

            if ($startAt === null) {
                continue;
            }

            $payload[] = [
                'provider_event_id' => $event->getId(),
                'summary' => $event->getSummary(),
                'start_at' => $startAt,
                'end_at' => $endAt,
                'raw_payload' => $event->toSimpleObject(),
            ];
        }

        return $this->events->upsertEvents($userId, 'google_calendar', $payload);
    }

    private function buildClient(): Client
    {
        $client = new Client();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($this->redirectUrl);
        $client->setScopes(array_filter(array_map('trim', explode(',', $this->scopes))));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }
}
