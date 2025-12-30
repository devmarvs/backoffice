<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Infrastructure\Integrations\GoogleCalendarService;
use App\Domain\Repository\IntegrationRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/integrations/google')]
final class IntegrationController extends BaseApiController
{
    public function __construct(
        #[Autowire('%app.google_success_redirect%')] private string $successRedirect,
        #[Autowire('%app.google_failure_redirect%')] private string $failureRedirect
    ) {
    }

    #[Route('/connect', methods: ['GET'])]
    public function connect(Request $request, GoogleCalendarService $google): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        if (!$google->isConfigured()) {
            return $this->jsonError('not_configured', 'Google integration is not configured.', 409);
        }

        $state = bin2hex(random_bytes(16));
        $session = $request->getSession();
        if ($session !== null) {
            $session->set('google_oauth_state', $state);
        }

        $url = $google->getAuthUrl($state);

        return $this->jsonSuccess(['url' => $url]);
    }

    #[Route('/callback', methods: ['GET'])]
    public function callback(Request $request, GoogleCalendarService $google): RedirectResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return new RedirectResponse($this->failureRedirect, 302);
        }

        $state = (string) $request->query->get('state', '');
        $code = (string) $request->query->get('code', '');

        $session = $request->getSession();
        $expectedState = $session?->get('google_oauth_state');
        if ($state === '' || $code === '' || $expectedState !== $state) {
            return new RedirectResponse($this->failureRedirect, 302);
        }

        try {
            $google->handleCallback($userId, $code);
        } catch (\Throwable $exception) {
            return new RedirectResponse($this->failureRedirect, 302);
        }

        return new RedirectResponse($this->successRedirect, 302);
    }

    #[Route('/sync', methods: ['POST'])]
    public function sync(Request $request, GoogleCalendarService $google): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        if (!$google->isConfigured()) {
            return $this->jsonError('not_configured', 'Google integration is not configured.', 409);
        }

        try {
            $count = $google->syncEvents($userId);
        } catch (\Throwable $exception) {
            return $this->jsonError('sync_failed', $exception->getMessage(), 400);
        }

        return $this->jsonSuccess(['imported' => $count]);
    }

    #[Route('', methods: ['DELETE'])]
    public function disconnect(Request $request, IntegrationRepositoryInterface $integrations): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $integrations->delete($userId, 'google_calendar');

        return $this->jsonSuccess(['disconnected' => true]);
    }
}
