<?php

namespace Tests\Feature;

use App\Models\RestaurantTable;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_request_factory_creates_valid_request(): void
    {
        $request = ServiceRequest::factory()->create();

        $this->assertInstanceOf(ServiceRequest::class, $request);
        $this->assertNotNull($request->table_id);

        $this->assertContains($request->kind, [
            'water',
            'bread',
            'bill',
            'waiter',
        ]);

        $this->assertSame('pending', $request->status);
        $this->assertNotNull($request->requested_at);
        $this->assertNull($request->acknowledged_by);
        $this->assertNull($request->acknowledged_at);
    }

    public function test_request_can_be_created_for_specific_table(): void
    {
        $table = RestaurantTable::factory()->create();

        $request = ServiceRequest::factory()
            ->forTable($table)
            ->create();

        $this->assertEquals($table->id, $request->table_id);
    }

    public function test_request_can_be_created_for_specific_session(): void
    {
        $session = TableSession::factory()->create();

        $request = ServiceRequest::factory()
            ->forSession($session)
            ->create();

        $this->assertEquals(
            $session->restaurant_table_id,
            $request->table_id
        );

        $this->assertEquals(
            $session->id,
            $request->table_session_id
        );
    }

    public function test_water_state_creates_water_request(): void
    {
        $request = ServiceRequest::factory()
            ->water()
            ->create();

        $this->assertSame('water', $request->kind);
    }

    public function test_bread_state_creates_bread_request(): void
    {
        $request = ServiceRequest::factory()
            ->bread()
            ->create();

        $this->assertSame('bread', $request->kind);
    }

    public function test_bill_state_creates_bill_request(): void
    {
        $request = ServiceRequest::factory()
            ->bill()
            ->create();

        $this->assertSame('bill', $request->kind);
    }

    public function test_waiter_state_creates_waiter_request(): void
    {
        $request = ServiceRequest::factory()
            ->waiter()
            ->create();

        $this->assertSame('waiter', $request->kind);
    }

    public function test_pending_state_creates_pending_request(): void
    {
        $request = ServiceRequest::factory()
            ->pending()
            ->create();

        $this->assertSame('pending', $request->status);
        $this->assertNull($request->acknowledged_by);
        $this->assertNull($request->acknowledged_at);
    }

    public function test_acknowledged_state_stores_staff_user(): void
    {
        $user = User::factory()->create();

        $request = ServiceRequest::factory()
            ->acknowledged($user)
            ->create();

        $this->assertSame('acknowledged', $request->status);
        $this->assertEquals($user->id, $request->acknowledged_by);
        $this->assertNotNull($request->acknowledged_at);
    }

    public function test_completed_state_creates_completed_request(): void
    {
        $request = ServiceRequest::factory()
            ->completed()
            ->create();

        $this->assertSame('completed', $request->status);
    }
}
