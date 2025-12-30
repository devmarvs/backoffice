<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\WorkEvent\WorkEventService;
use App\Domain\Enum\WorkEventType;
use App\Domain\Repository\ClientRepositoryInterface;
use App\Domain\Repository\UserSettingsRepositoryInterface;
use DateTimeImmutable;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/onboarding')]
final class OnboardingController extends BaseApiController
{
    #[Route('', methods: ['POST'])]
    public function complete(
        Request $request,
        UserSettingsRepositoryInterface $settings,
        ClientRepositoryInterface $clients,
        WorkEventService $workEvents
    ): JsonResponse {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        try {
            $payload = $this->parseJson($request);
        } catch (JsonException $exception) {
            return $this->jsonError('invalid_json', $exception->getMessage(), 400);
        }

        $settingsData = [];
        foreach (['business_type', 'charge_model', 'default_rate_cents', 'default_currency', 'follow_up_days', 'invoice_reminder_days', 'onboarding_note'] as $key) {
            if (array_key_exists($key, $payload)) {
                $settingsData[$key] = $payload[$key];
            }
        }

        if ($settingsData !== []) {
            $settings->upsert($userId, $settingsData);
        }

        $firstClientName = isset($payload['first_client_name']) ? trim((string) $payload['first_client_name']) : '';
        $startAtRaw = isset($payload['first_start_at']) ? (string) $payload['first_start_at'] : '';
        $durationMinutes = isset($payload['first_duration_minutes']) ? (int) $payload['first_duration_minutes'] : 0;

        if ($firstClientName !== '' && $startAtRaw !== '' && $durationMinutes > 0) {
            try {
                $startAt = new DateTimeImmutable($startAtRaw);
            } catch (\Exception $exception) {
                return $this->jsonError('invalid_start_at', 'first_start_at must be a valid datetime.', 422);
            }

            $client = $clients->create(
                $userId,
                $firstClientName,
                isset($payload['first_client_email']) ? trim((string) $payload['first_client_email']) : null,
                isset($payload['first_client_phone']) ? trim((string) $payload['first_client_phone']) : null
            );

            $rateCents = null;
            if (array_key_exists('first_rate_cents', $payload) && $payload['first_rate_cents'] !== null) {
                $rateCents = (int) $payload['first_rate_cents'];
            }

            $currency = isset($payload['first_currency']) ? strtoupper(trim((string) $payload['first_currency'])) : null;
            $billable = array_key_exists('first_billable', $payload)
                ? filter_var($payload['first_billable'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : true;

            if ($billable === null) {
                return $this->jsonError('invalid_billable', 'first_billable must be a boolean.', 422);
            }

            $type = isset($payload['first_type']) ? (string) $payload['first_type'] : WorkEventType::Session->value;
            $allowedTypes = array_map(fn (WorkEventType $item) => $item->value, WorkEventType::cases());
            if (!in_array($type, $allowedTypes, true)) {
                return $this->jsonError('invalid_type', 'first_type is invalid.', 422);
            }

            $workEvents->log(
                [
                    'user_id' => $userId,
                    'client_id' => (int) $client['id'],
                    'type' => $type,
                    'start_at' => $startAt->format('Y-m-d H:i:sP'),
                    'duration_minutes' => $durationMinutes,
                    'billable' => $billable,
                    'notes' => isset($payload['first_notes']) ? trim((string) $payload['first_notes']) : null,
                ],
                $rateCents,
                $currency ?: null
            );
        }

        return $this->jsonSuccess(['completed' => true]);
    }
}
