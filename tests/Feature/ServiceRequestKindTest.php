<?php

namespace Tests\Feature;

use App\Enums\ServiceRequestKind;
use Tests\TestCase;

class ServiceRequestKindTest extends TestCase
{
    public function test_all_service_request_kinds_exist(): void
    {
        $this->assertSame(
            [
                'water',
                'bread',
                'bill',
                'waiter',
                'cleaning',
                'other',
            ],
            array_map(
                fn (ServiceRequestKind $kind): string => $kind->value,
                ServiceRequestKind::cases(),
            ),
        );
    }

    public function test_each_kind_has_label_and_color(): void
    {
        foreach (ServiceRequestKind::cases() as $kind) {
            $this->assertNotSame('', $kind->label());
            $this->assertNotSame('', $kind->color());
        }
    }
}
