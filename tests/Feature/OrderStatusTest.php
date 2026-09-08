<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_all_legal_transitions_are_allowed(): void
    {
        $legalTransitions = [
            OrderStatus::PLACED->value => [
                OrderStatus::PENDING,
                OrderStatus::CANCELLED,
            ],

            OrderStatus::PENDING->value => [
                OrderStatus::PREPARING,
                OrderStatus::CANCELLED,
            ],

            OrderStatus::PREPARING->value => [
                OrderStatus::READY,
                OrderStatus::CANCELLED,
            ],

            OrderStatus::READY->value => [
                OrderStatus::SERVED,
            ],

            OrderStatus::SERVED->value => [
                OrderStatus::PAID,
            ],
        ];

        foreach ($legalTransitions as $currentValue => $nextStatuses) {
            $current = OrderStatus::from($currentValue);

            foreach ($nextStatuses as $next) {
                $this->assertTrue(
                    $current->canTransitionTo($next),
                    "{$current->value} should be allowed to transition to {$next->value}."
                );
            }
        }
    }

    public function test_illegal_transitions_are_rejected(): void
    {
        $allStatuses = OrderStatus::cases();

        foreach ($allStatuses as $current) {
            foreach ($allStatuses as $next) {
                if ($current === $next) {
                    continue;
                }

                if (! $current->canTransitionTo($next)) {
                    $this->assertFalse(
                        $current->canTransitionTo($next),
                        "{$current->value} should not transition to {$next->value}."
                    );
                }
            }
        }
    }
}