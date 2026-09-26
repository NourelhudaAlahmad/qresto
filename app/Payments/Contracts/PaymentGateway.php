<?php

namespace App\Payments\Contracts;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\ValueObjects\Intent;
use App\Payments\ValueObjects\Result;
use App\Support\Money;

interface PaymentGateway
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function createIntent(
        Order $order,
        Money $amount,
        array $meta = [],
    ): Intent;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function confirm(
        string $intentId,
        array $payload = [],
    ): Result;

    public function capture(
        string $intentId,
        Money $amount,
    ): Result;

    public function refund(
        Payment $payment,
        Money $amount,
    ): Result;

    public function status(string $intentId): Result;
}
