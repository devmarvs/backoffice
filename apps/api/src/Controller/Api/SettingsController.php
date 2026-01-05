<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Repository\UserSettingsRepositoryInterface;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings')]
final class SettingsController extends BaseApiController
{
    #[Route('', methods: ['GET'])]
    public function get(Request $request, UserSettingsRepositoryInterface $settings): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $row = $settings->getByUserId($userId);

        if ($row === null) {
            return $this->jsonSuccess([
                'user_id' => $userId,
                'default_currency' => 'EUR',
                'follow_up_days' => null,
                'invoice_reminder_days' => null,
                'last_reminder_run_at' => null,
            ]);
        }

        $row = $this->normalizeDates($row, ['created_at', 'updated_at', 'last_reminder_run_at']);

        return $this->jsonSuccess($row);
    }

    #[Route('', methods: ['PUT'])]
    public function update(Request $request, UserSettingsRepositoryInterface $settings): JsonResponse
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

        $allowed = [
            'business_type',
            'charge_model',
            'default_rate_cents',
            'default_currency',
            'follow_up_days',
            'invoice_reminder_days',
            'onboarding_note',
        ];

        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = $payload[$key];
            }
        }

        if (isset($data['charge_model']) && $data['charge_model'] !== null) {
            $allowedModels = ['per_session', 'package', 'monthly'];
            if (!in_array($data['charge_model'], $allowedModels, true)) {
                return $this->jsonError('invalid_charge_model', 'Charge model is invalid.', 422);
            }
        }

        if (isset($data['default_currency']) && $data['default_currency'] !== null) {
            $currency = strtoupper(trim((string) $data['default_currency']));
            if (strlen($currency) !== 3) {
                return $this->jsonError('invalid_currency', 'Currency must be a 3-letter code.', 422);
            }
            $data['default_currency'] = $currency;
        }

        if (isset($data['default_rate_cents']) && $data['default_rate_cents'] !== null) {
            $rate = (int) $data['default_rate_cents'];
            if ($rate < 0) {
                return $this->jsonError('invalid_rate', 'default_rate_cents must be >= 0.', 422);
            }
            $data['default_rate_cents'] = $rate;
        }

        foreach (['follow_up_days', 'invoice_reminder_days'] as $key) {
            if (isset($data[$key]) && $data[$key] !== null) {
                $value = (int) $data[$key];
                if ($value < 0) {
                    return $this->jsonError('invalid_days', sprintf('%s must be >= 0.', $key), 422);
                }
                $data[$key] = $value;
            }
        }

        $row = $settings->upsert($userId, $data);
        $row = $this->normalizeDates($row, ['created_at', 'updated_at']);

        return $this->jsonSuccess($row);
    }
}
