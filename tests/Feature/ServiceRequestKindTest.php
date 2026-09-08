<?php

namespace Tests\Feature;

use App\Enums\ServiceRequestKind;
use PHPUnit\Framework\TestCase;

class ServiceRequestKindTest extends TestCase
{
    public function test_all_service_request_kinds_exist(): void
    {
        $this->assertSame(
            [
                'water',
                'cutlery',
                'bill',
                'waiter',
                'cleaning',
                'other',
            ],
            array_map(
                fn (ServiceRequestKind $kind) => $kind->value,
                ServiceRequestKind::cases(),
            ),
        );
    }

    public function test_every_kind_has_label_and_color(): void
    {
        foreach (ServiceRequestKind::cases() as $kind) {
            $this->assertNotEmpty($kind->label());
            $this->assertNotEmpty($kind->color());
        }
    }
}