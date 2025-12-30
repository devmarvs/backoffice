<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Repository\ClientRepositoryInterface;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/clients')]
final class ClientController extends BaseApiController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request, ClientRepositoryInterface $clients): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $search = $request->query->get('search');
        $rows = $clients->search($userId, $search !== null ? (string) $search : null);

        $rows = array_map(
            fn (array $row) => $this->normalizeDates($row, ['created_at', 'updated_at']),
            $rows
        );

        return $this->jsonSuccess($rows);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, ClientRepositoryInterface $clients): JsonResponse
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

        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        $email = isset($payload['email']) ? trim((string) $payload['email']) : null;
        $phone = isset($payload['phone']) ? trim((string) $payload['phone']) : null;

        if ($name === '') {
            return $this->jsonError('invalid_name', 'Client name is required.', 422);
        }

        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonError('invalid_email', 'Client email is invalid.', 422);
        }

        $client = $clients->create($userId, $name, $email ?: null, $phone ?: null);
        $client = $this->normalizeDates($client, ['created_at', 'updated_at']);

        return $this->jsonSuccess($client, 201);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(Request $request, ClientRepositoryInterface $clients, int $id): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $client = $clients->findById($userId, $id);
        if ($client === null) {
            return $this->jsonError('not_found', 'Client not found.', 404);
        }

        $client = $this->normalizeDates($client, ['created_at', 'updated_at']);

        return $this->jsonSuccess($client);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(Request $request, ClientRepositoryInterface $clients, int $id): JsonResponse
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

        if (isset($payload['email']) && $payload['email'] !== null) {
            $email = trim((string) $payload['email']);
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->jsonError('invalid_email', 'Client email is invalid.', 422);
            }
            $payload['email'] = $email;
        }

        if (isset($payload['name'])) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                return $this->jsonError('invalid_name', 'Client name is required.', 422);
            }
            $payload['name'] = $name;
        }

        $client = $clients->update($userId, $id, $payload);
        if ($client === null) {
            return $this->jsonError('not_found', 'Client not found.', 404);
        }

        $client = $this->normalizeDates($client, ['created_at', 'updated_at']);

        return $this->jsonSuccess($client);
    }
}
