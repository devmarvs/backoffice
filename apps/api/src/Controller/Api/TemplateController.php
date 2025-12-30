<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Templates\TemplateService;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/templates')]
final class TemplateController extends BaseApiController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request, TemplateService $templates): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        return $this->jsonSuccess($templates->listForUser($userId));
    }

    #[Route('/{type}', methods: ['PUT'])]
    public function upsert(Request $request, TemplateService $templates, string $type): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        try {
            $payload = $this->parseJson($request);
        } catch (JsonException $exception) {
            return $this->jsonError('invalid_json', $exception->getMessage(), 400);
        }

        $allowed = ['follow_up', 'payment_reminder', 'no_show'];
        if (!in_array($type, $allowed, true)) {
            return $this->jsonError('invalid_type', 'Template type is invalid.', 422);
        }

        $body = isset($payload['body']) ? trim((string) $payload['body']) : '';
        if ($body === '') {
            return $this->jsonError('invalid_body', 'Template body is required.', 422);
        }

        $subject = isset($payload['subject']) ? trim((string) $payload['subject']) : null;
        $template = $templates->upsert($userId, $type, $subject ?: null, $body);

        return $this->jsonSuccess($template);
    }
}
