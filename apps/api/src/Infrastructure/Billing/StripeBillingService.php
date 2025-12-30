<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing;

use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class StripeBillingService
{
    private StripeClient $client;

    public function __construct(
        #[Autowire('%app.stripe_secret_key%')] private string $secretKey,
        #[Autowire('%app.stripe_price_id%')] private string $priceId,
        #[Autowire('%app.stripe_success_url%')] private string $successUrl,
        #[Autowire('%app.stripe_cancel_url%')] private string $cancelUrl,
        #[Autowire('%app.stripe_webhook_secret%')] private string $webhookSecret,
        #[Autowire('%app.stripe_portal_return_url%')] private string $portalReturnUrl
    ) {
        $this->client = new StripeClient($this->secretKey ?: '');
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && $this->priceId !== '';
    }

    public function isPortalConfigured(): bool
    {
        return $this->secretKey !== '' && $this->portalReturnUrl !== '';
    }

    public function createCheckoutSession(int $userId, string $email): array
    {
        $session = $this->client->checkout->sessions->create([
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price' => $this->priceId,
                    'quantity' => 1,
                ],
            ],
            'customer_email' => $email,
            'client_reference_id' => (string) $userId,
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
        ]);

        return [
            'id' => $session->id,
            'url' => $session->url,
        ];
    }

    public function createPaymentLink(int $amountCents, string $currency, string $description): array
    {
        $link = $this->client->paymentLinks->create([
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => [
                            'name' => $description,
                        ],
                        'unit_amount' => $amountCents,
                    ],
                    'quantity' => 1,
                ],
            ],
        ]);

        return [
            'id' => $link->id,
            'url' => $link->url,
        ];
    }

    public function createPortalSession(string $customerId): array
    {
        $session = $this->client->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $this->portalReturnUrl,
        ]);

        return [
            'url' => $session->url,
        ];
    }

    public function constructEvent(string $payload, string $signature): object
    {
        return Webhook::constructEvent($payload, $signature, $this->webhookSecret);
    }
}
