<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Gateways\FakeGateway;
use InvalidArgumentException;

final class PaymentManager
{
    public function driver(?string $driver = null): PaymentGateway
    {
        $driver ??= (string) config(
            'qresto.payments.driver',
            'fake',
        );

        return match ($driver) {
            'fake' => new FakeGateway(
                latencyMs: (int) config(
                    'qresto.payments.fake.latency_ms',
                    0,
                ),
            ),

            default => throw new InvalidArgumentException(
                "Unsupported payment gateway driver [{$driver}].",
            ),
        };
    }
}
