<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing;

use App\Infrastructure\Http\CurlHttpClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PayPalBillingService
{
    private const DEFAULT_BRAND = 'BackOffice Autopilot';

    public function __construct(
        private CurlHttpClient $http,
        #[Autowire('%app.paypal_client_id%')] private string $clientId,
        #[Autowire('%app.paypal_client_secret%')] private string $clientSecret,
        #[Autowire('%app.paypal_plan_id%')] private string $planId,
        #[Autowire('%app.paypal_plan_id_starter%')] private string $planIdStarter,
        #[Autowire('%app.paypal_plan_id_pro%')] private string $planIdPro,
        #[Autowire('%app.paypal_success_url%')] private string $successUrl,
        #[Autowire('%app.paypal_cancel_url%')] private string $cancelUrl,
        #[Autowire('%app.paypal_environment%')] private string $environment,
        #[Autowire('%app.paypal_manage_url%')] private string $manageUrl,
        #[Autowire('%app.paypal_brand_name%')] private string $brandName
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->hasAnyPlanId();
    }

    public function isPlanConfigured(string $plan): bool
    {
        return $this->resolvePlanId($plan) !== null;
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

    public function createSubscription(int $userId, string $plan): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('PayPal is not configured.');
        }

        $planId = $this->resolvePlanId($plan);
        if ($planId === null) {
            throw new \RuntimeException('PayPal plan is not configured for this plan.');
        }

        $payload = [
            'plan_id' => $planId,
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
        $body = $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR) : null;

        $response = $this->http->request($method, $url, $headers, $body);
        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            $data = [];
        }

        if ($response['status'] >= 400) {
            $message = $data['message'] ?? 'PayPal request failed.';
            throw new \RuntimeException($message);
        }

        return $data;
    }

    private function requestToken(): string
    {
        $url = $this->baseUrl() . '/v1/oauth2/token';
        $credentials = base64_encode($this->clientId . ':' . $this->clientSecret);

        $headers = [
            'Accept: application/json',
            'Accept-Language: en_US',
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $credentials,
        ];

        $response = $this->http->request(
            'POST',
            $url,
            $headers,
            'grant_type=client_credentials'
        );

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            $data = [];
        }

        if ($response['status'] >= 400) {
            $message = $data['error_description'] ?? 'PayPal auth failed.';
            throw new \RuntimeException($message);
        }

        if (!isset($data['access_token'])) {
            throw new \RuntimeException('PayPal access token missing.');
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

    private function resolvePlanId(string $plan): ?string
    {
        $plan = strtolower(trim($plan));
        if ($plan === 'starter' && $this->planIdStarter !== '') {
            return $this->planIdStarter;
        }
        if ($plan === 'pro' && $this->planIdPro !== '') {
            return $this->planIdPro;
        }

        return $this->planId !== '' ? $this->planId : null;
    }

    private function hasAnyPlanId(): bool
    {
        return $this->planId !== '' || $this->planIdStarter !== '' || $this->planIdPro !== '';
    }
}
