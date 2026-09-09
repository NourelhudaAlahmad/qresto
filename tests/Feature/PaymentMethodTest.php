<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    public function test_all_payment_methods_exist(): void
    {
        $this->assertSame(
            [
                'card',
                'wallet',
                'cash',
                'pos',
            ],
            array_map(
                fn (PaymentMethod $method): string => $method->value,
                PaymentMethod::cases(),
            ),
        );
    }

    public function test_each_payment_method_has_label_and_color(): void
    {
        foreach (PaymentMethod::cases() as $method) {
            $this->assertNotSame('', $method->label());
            $this->assertNotSame('', $method->color());
        }
    }

    public function test_payment_method_labels_are_translated(): void
    {
        app()->setLocale('en');

        $this->assertSame(
            'Card',
            PaymentMethod::CARD->label(),
        );

        $this->assertSame(
            'Wallet',
            PaymentMethod::WALLET->label(),
        );

        app()->setLocale('ar');

        $this->assertSame(
            'بطاقة',
            PaymentMethod::CARD->label(),
        );

        $this->assertSame(
            'محفظة',
            PaymentMethod::WALLET->label(),
        );
    }
}
