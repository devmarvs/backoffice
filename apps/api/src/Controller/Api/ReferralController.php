<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Repository\ReferralRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/referrals')]
final class ReferralController extends BaseApiController
{
    #[Route('/me', methods: ['GET'])]
    public function me(Request $request, ReferralRepositoryInterface $referrals): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $code = $referrals->findCodeForUser($userId);
        if ($code === null) {
            $codeValue = strtoupper(bin2hex(random_bytes(4)));
            $code = $referrals->createCode($userId, $codeValue);
        }

        $list = $referrals->listByReferrer($userId);
        $code = $this->normalizeDates($code, ['created_at']);

        return $this->jsonSuccess([
            'code' => $code,
            'referrals' => $list,
        ]);
    }
}
