<?php

declare(strict_types=1);

namespace App\Controller\Api;

use DateTimeImmutable;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseApiController extends AbstractController
{
    protected function parseJson(Request $request): array
    {
        $content = trim($request->getContent());
        if ($content === '') {
            return [];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JsonException('Invalid JSON payload.', 0, $exception);
        }

        if (!is_array($data)) {
            throw new JsonException('JSON payload must be an object.');
        }

        return $data;
    }

    protected function requireUserId(Request $request): ?int
    {
        $session = $request->getSession();
        if ($session === null) {
            return null;
        }

        $userId = $session->get('user_id');
        if ($userId === null) {
            return null;
        }

        return (int) $userId;
    }

    protected function jsonSuccess(mixed $data, int $status = 200): JsonResponse
    {
        return $this->json(['data' => $data], $status);
    }

    protected function jsonError(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return $this->json(
            ['error' => ['code' => $code, 'message' => $message, 'details' => $details]],
            $status
        );
    }

    protected function normalizeDates(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            if (!empty($row[$field])) {
                $row[$field] = (new DateTimeImmutable((string) $row[$field]))->format(DateTimeImmutable::ATOM);
            }
        }

        return $row;
    }

    protected function csvResponse(array $headers, array $rows, string $filename): Response
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create CSV output.');
        }

        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
