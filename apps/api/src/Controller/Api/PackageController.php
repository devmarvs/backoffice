<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Repository\ClientRepositoryInterface;
use App\Domain\Repository\PackageRepositoryInterface;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/packages')]
final class PackageController extends BaseApiController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request, PackageRepositoryInterface $packages): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $clientId = $request->query->get('clientId');
        if ($clientId === null || (int) $clientId <= 0) {
            return $this->jsonError('invalid_client', 'clientId is required.', 422);
        }

        $rows = $packages->listByClient($userId, (int) $clientId);
        $rows = array_map(
            fn (array $row) => $this->normalizeDates($row, ['created_at']),
            $rows
        );

        return $this->jsonSuccess($rows);
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        PackageRepositoryInterface $packages,
        ClientRepositoryInterface $clients
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

        $clientId = isset($payload['client_id']) ? (int) $payload['client_id'] : 0;
        if ($clientId <= 0 || $clients->findById($userId, $clientId) === null) {
            return $this->jsonError('invalid_client', 'Client not found.', 404);
        }

        $title = isset($payload['title']) ? trim((string) $payload['title']) : '';
        if ($title === '') {
            return $this->jsonError('invalid_title', 'Package title is required.', 422);
        }

        $totalSessions = isset($payload['total_sessions']) ? (int) $payload['total_sessions'] : 0;
        if ($totalSessions <= 0) {
            return $this->jsonError('invalid_sessions', 'total_sessions must be > 0.', 422);
        }

        $usedSessions = isset($payload['used_sessions']) ? (int) $payload['used_sessions'] : 0;
        if ($usedSessions < 0 || $usedSessions > $totalSessions) {
            return $this->jsonError('invalid_sessions', 'used_sessions must be between 0 and total_sessions.', 422);
        }

        $currency = isset($payload['currency']) ? strtoupper(trim((string) $payload['currency'])) : 'EUR';
        if (strlen($currency) !== 3) {
            return $this->jsonError('invalid_currency', 'currency must be a 3-letter code.', 422);
        }

        $priceCents = null;
        if (array_key_exists('price_cents', $payload) && $payload['price_cents'] !== null) {
            $priceCents = (int) $payload['price_cents'];
            if ($priceCents < 0) {
                return $this->jsonError('invalid_price', 'price_cents must be >= 0.', 422);
            }
        }

        $package = $packages->create([
            'user_id' => $userId,
            'client_id' => $clientId,
            'title' => $title,
            'total_sessions' => $totalSessions,
            'used_sessions' => $usedSessions,
            'price_cents' => $priceCents,
            'currency' => $currency,
        ]);

        $package = $this->normalizeDates($package, ['created_at']);

        return $this->jsonSuccess($package, 201);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(Request $request, PackageRepositoryInterface $packages, int $id): JsonResponse
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

        if (isset($payload['total_sessions']) && $payload['total_sessions'] !== null) {
            $payload['total_sessions'] = (int) $payload['total_sessions'];
            if ($payload['total_sessions'] <= 0) {
                return $this->jsonError('invalid_sessions', 'total_sessions must be > 0.', 422);
            }
        }

        if (isset($payload['used_sessions']) && $payload['used_sessions'] !== null) {
            $payload['used_sessions'] = (int) $payload['used_sessions'];
            if ($payload['used_sessions'] < 0) {
                return $this->jsonError('invalid_sessions', 'used_sessions must be >= 0.', 422);
            }
        }

        if (isset($payload['currency']) && $payload['currency'] !== null) {
            $currency = strtoupper(trim((string) $payload['currency']));
            if (strlen($currency) !== 3) {
                return $this->jsonError('invalid_currency', 'currency must be a 3-letter code.', 422);
            }
            $payload['currency'] = $currency;
        }

        if (isset($payload['price_cents']) && $payload['price_cents'] !== null) {
            $priceCents = (int) $payload['price_cents'];
            if ($priceCents < 0) {
                return $this->jsonError('invalid_price', 'price_cents must be >= 0.', 422);
            }
            $payload['price_cents'] = $priceCents;
        }

        $package = $packages->update($userId, $id, $payload);
        if ($package === null) {
            return $this->jsonError('not_found', 'Package not found.', 404);
        }

        if (isset($payload['used_sessions'], $payload['total_sessions'])) {
            if ((int) $payload['used_sessions'] > (int) $payload['total_sessions']) {
                return $this->jsonError('invalid_sessions', 'used_sessions cannot exceed total_sessions.', 422);
            }
        }

        $package = $this->normalizeDates($package, ['created_at']);

        return $this->jsonSuccess($package);
    }

    #[Route('/{id}/use', methods: ['POST'])]
    public function useSession(Request $request, PackageRepositoryInterface $packages, int $id): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $package = $packages->findById($userId, $id);
        if ($package === null) {
            return $this->jsonError('not_found', 'Package not found.', 404);
        }

        if ((int) $package['used_sessions'] >= (int) $package['total_sessions']) {
            return $this->jsonError('package_empty', 'No remaining sessions.', 422);
        }

        $updated = $packages->incrementUsedSessions($id);
        $updated = $this->normalizeDates($updated, ['created_at']);

        return $this->jsonSuccess($updated);
    }
}
