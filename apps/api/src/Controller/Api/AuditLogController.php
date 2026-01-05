<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Repository\AuditLogRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/audit-logs')]
final class AuditLogController extends BaseApiController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request, AuditLogRepositoryInterface $auditLogs): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $limit = (int) $request->query->get('limit', 10);
        if ($limit <= 0) {
            $limit = 10;
        }

        $rows = $auditLogs->listRecent($userId, min($limit, 50));
        $rows = array_map(
            function (array $row): array {
                $row = $this->normalizeDates($row, ['created_at']);
                if (isset($row['metadata']) && is_string($row['metadata'])) {
                    $decoded = json_decode($row['metadata'], true);
                    if (is_array($decoded)) {
                        $row['metadata'] = $decoded;
                    }
                }
                return $row;
            },
            $rows
        );

        return $this->jsonSuccess($rows);
    }
}
