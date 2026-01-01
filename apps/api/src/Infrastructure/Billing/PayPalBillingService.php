<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PayPalBillingService
{
    private const DEFAULT_BRAND = 'BackOffice Autopilot';

    public function __construct(
        #[Autowire('%app.paypal_client_id%')] private string $clientId,
        #[Autowire('%app.paypal_client_secret%')] private string $clientSecret,
        #[Autowire('%app.paypal_plan_id%')] private string $planId,
        #[Autowire('%app.paypal_success_url%')] private string $successUrl,
        #[Autowire('%app.paypal_cancel_url%')] private string $cancelUrl,
        #[Autowire('%app.paypal_environment%')] private string $environment,
        #[Autowire('%app.paypal_manage_url%')] private string $manageUrl,
        #[Autowire('%app.paypal_brand_name%')] private string $brandName
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->planId !== '';
    }

    public function isManageConfigured(): bool
    {
        return $this->getManageUrl() !== '';
    }

    public function getManageUrl(): string
    {
        if ($this->manageUrl !== '') {
            return $this->manageUrl;
        }

        return $this->isLive() ? 'https://www.paypal.com/myaccount/autopay/' : 'https://www.sandbox.paypal.com/myaccount/autopay/';
    }

    public function createSubscription(int $userId): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('PayPal is not configured.');
        }

        $payload = [
            'plan_id' => $this->planId,
            'custom_id' => (string) $userId,
            'application_context' => [
                'brand_name' => $this->brandName !== '' ? $this->brandName : self::DEFAULT_BRAND,
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => $this->successUrl,
                'cancel_url' => $this->cancelUrl,
            ],
        ];

        $data = $this->request('POST', '/v1/billing/subscriptions', $payload);
        $approvalUrl = $this->findLink($data, 'approve');

        if ($approvalUrl === null || !isset($data['id'])) {
            throw new \RuntimeException('PayPal approval link is missing.');
        }

        return [
            'id' => (string) $data['id'],
            'approve_url' => $approvalUrl,
        ];
    }

    public function getSubscription(string $subscriptionId): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('PayPal is not configured.');
        }

        return $this->request('GET', sprintf('/v1/billing/subscriptions/%s', $subscriptionId));
    }

    private function findLink(array $data, string $rel): ?string
    {
        $links = $data['links'] ?? [];
        foreach ($links as $link) {
            if (($link['rel'] ?? '') === $rel && isset($link['href'])) {
                return (string) $link['href'];
            }
        }

        return null;
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $token = $this->requestToken();
        $url = $this->baseUrl() . $path;

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize PayPal request.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('PayPal request failed: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $data = [];
        }

        if ($status >= 400) {
            $message = $data['message'] ?? 'PayPal request failed.';
            throw new \RuntimeException($message);
        }

        return $data;
    }

    private function requestToken(): string
    {
        $url = $this->baseUrl() . '/v1/oauth2/token';

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize PayPal token request.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $this->clientId . ':' . $this->clientSecret,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en_US',
            ],
        ];

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('PayPal auth failed: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['access_token'])) {
            throw new \RuntimeException('PayPal access token missing.');
        }

        if ($status >= 400) {
            $message = $data['error_description'] ?? 'PayPal auth failed.';
            throw new \RuntimeException($message);
        }

        return (string) $data['access_token'];
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function isLive(): bool
    {
        return strtolower($this->environment) === 'live';
    }
}
