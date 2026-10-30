<?php

namespace Tests\Support;

use RuntimeException;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * Stands in for the network under the real Stripe SDK (#35). The SDK
 * builds requests and parses responses (and throws its card exceptions)
 * exactly as it would against api.stripe.com.
 */
final class FakeStripe implements ClientInterface
{
    /** @var list<array{method: string, url: string, headers: array<int, string>, params: array<string, mixed>}> */
    public array $requests = [];

    /** @var list<array{0: string, 1: int, 2: array<string, string>}> */
    private array $responses = [];

    public static function install(): self
    {
        $fake = new self;

        config(['qresto.payments.stripe.secret' => 'sk_test_fake']);
        ApiRequestor::setHttpClient($fake);

        return $fake;
    }

    public static function uninstall(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function respond(array $body, int $status = 200): self
    {
        $this->responses[] = [json_encode($body, JSON_THROW_ON_ERROR), $status, ['request-id' => 'req_fake']];

        return $this;
    }

    public function decline(string $code, ?string $declineCode = null, string $intentId = 'pi_declined'): self
    {
        return $this->respond([
            'error' => array_filter([
                'type' => 'card_error',
                'code' => $code,
                'decline_code' => $declineCode,
                'message' => 'Stripe says no.',
                'payment_intent' => ['id' => $intentId, 'object' => 'payment_intent', 'status' => 'requires_payment_method'],
            ]),
        ], 402);
    }

    /**
     * @return array<string, mixed>
     */
    public static function intent(string $id, string $status, array $extra = []): array
    {
        return [
            'id' => $id,
            'object' => 'payment_intent',
            'status' => $status,
            'amount' => 2000,
            'currency' => 'usd',
            'client_secret' => "{$id}_secret_abc",
            'last_payment_error' => null,
            ...$extra,
        ];
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'headers' => $headers, 'params' => $params];

        if ($this->responses === []) {
            throw new RuntimeException("Unexpected Stripe call: {$method} {$absUrl}");
        }

        return array_shift($this->responses);
    }

    /**
     * A Stripe-Signature header for this payload, the way Stripe signs it.
     */
    public static function sign(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }
}
