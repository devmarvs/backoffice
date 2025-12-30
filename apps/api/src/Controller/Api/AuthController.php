<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Repository\ReferralRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final class AuthController extends BaseApiController
{
    #[Route('/register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepositoryInterface $users,
        ReferralRepositoryInterface $referrals
    ): JsonResponse
    {
        try {
            $payload = $this->parseJson($request);
        } catch (JsonException $exception) {
            return $this->jsonError('invalid_json', $exception->getMessage(), 400);
        }

        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $password = (string) ($payload['password'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonError('invalid_email', 'A valid email is required.', 422);
        }

        if (mb_strlen($password) < 8) {
            return $this->jsonError('invalid_password', 'Password must be at least 8 characters.', 422);
        }

        if ($users->findByEmail($email) !== null) {
            return $this->jsonError('email_taken', 'Email is already registered.', 409);
        }

        $user = $users->create($email, password_hash($password, PASSWORD_DEFAULT));

        $referralCode = isset($payload['referral_code']) ? trim((string) $payload['referral_code']) : '';
        if ($referralCode !== '') {
            $code = $referrals->findCode($referralCode);
            if ($code !== null) {
                $referrals->createReferral((int) $code['user_id'], (int) $user['id'], $referralCode, 'accepted');
            }
        }

        $session = $request->getSession();
        if ($session !== null) {
            $session->migrate(true);
            $session->set('user_id', $user['id'] ?? null);
        }

        $user = $this->normalizeDates($user, ['created_at']);

        return $this->jsonSuccess($user, 201);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request, UserRepositoryInterface $users): JsonResponse
    {
        try {
            $payload = $this->parseJson($request);
        } catch (JsonException $exception) {
            return $this->jsonError('invalid_json', $exception->getMessage(), 400);
        }

        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $password = (string) ($payload['password'] ?? '');

        $user = $email !== '' ? $users->findByEmail($email) : null;
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return $this->jsonError('invalid_credentials', 'Email or password is incorrect.', 401);
        }

        $session = $request->getSession();
        if ($session !== null) {
            $session->migrate(true);
            $session->set('user_id', $user['id']);
        }

        $user = $this->normalizeDates($user, ['created_at']);
        unset($user['password_hash']);

        return $this->jsonSuccess($user);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $session = $request->getSession();
        if ($session !== null) {
            $session->remove('user_id');
        }

        return $this->jsonSuccess(['logged_out' => true]);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(Request $request, UserRepositoryInterface $users): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $user = $users->findById($userId);
        if ($user === null) {
            return $this->jsonError('not_found', 'User not found.', 404);
        }

        $user = $this->normalizeDates($user, ['created_at']);

        return $this->jsonSuccess($user);
    }
}
