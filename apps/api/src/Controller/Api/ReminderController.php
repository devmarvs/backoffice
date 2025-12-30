<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Reminders\ReminderService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reminders')]
final class ReminderController extends BaseApiController
{
    #[Route('/run', methods: ['POST'])]
    public function run(Request $request, ReminderService $reminders): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $count = $reminders->runForUser($userId);

        return $this->jsonSuccess(['created' => $count]);
    }
}
