<?php

namespace Tests\Feature;

use App\Support\ElapsedTime;
use Tests\TestCase;

class ElapsedTimeTest extends TestCase
{
    public function test_elapsed_time_is_normal_before_warn_threshold(): void
    {
        $since = now()->subMinutes(13);

        $this->assertSame(
            ElapsedTime::NORMAL,
            ElapsedTime::level($since),
        );
    }

    public function test_elapsed_time_becomes_warn_at_exact_warn_threshold(): void
    {
        $since = now()->subMinutes(14);

        $this->assertSame(
            ElapsedTime::WARN,
            ElapsedTime::level($since),
        );
    }

    public function test_elapsed_time_is_warn_before_late_threshold(): void
    {
        $since = now()->subMinutes(24);

        $this->assertSame(
            ElapsedTime::WARN,
            ElapsedTime::level($since),
        );
    }

    public function test_elapsed_time_becomes_late_at_exact_late_threshold(): void
    {
        $since = now()->subMinutes(25);

        $this->assertSame(
            ElapsedTime::LATE,
            ElapsedTime::level($since),
        );
    }

    public function test_elapsed_time_honors_config_overrides(): void
    {
        config()->set('qresto.sla.warn_minutes', 10);
        config()->set('qresto.sla.late_minutes', 20);

        $this->assertSame(
            ElapsedTime::NORMAL,
            ElapsedTime::level(now()->subMinutes(9)),
        );

        $this->assertSame(
            ElapsedTime::WARN,
            ElapsedTime::level(now()->subMinutes(10)),
        );

        $this->assertSame(
            ElapsedTime::LATE,
            ElapsedTime::level(now()->subMinutes(20)),
        );
    }
}
