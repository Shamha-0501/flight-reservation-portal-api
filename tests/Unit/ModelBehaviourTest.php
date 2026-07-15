<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserAuthToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ModelBehaviourTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_boot_generates_a_uuid_key_and_status_helpers_work(): void
    {
        $tenant = Tenant::create([
            'name' => 'Demo Agency',
            'status' => 'pending',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);

        $this->assertNotEmpty($tenant->key);
        $this->assertTrue($tenant->isPending());
        $this->assertFalse($tenant->isActive());
        $this->assertFalse($tenant->isSuspended());
        $this->assertFalse($tenant->isRejected());
    }

    public function test_order_currency_accessors_fall_back_to_default_currency(): void
    {
        config(['finance.default_currency' => 'LKR']);

        $order = new Order([
            'base_currency' => null,
            'tax_currency' => null,
            'total_currency' => null,
        ]);

        $this->assertSame('LKR', $order->base_currency);
        $this->assertSame('LKR', $order->tax_currency);
        $this->assertSame('LKR', $order->total_currency);
    }

    public function test_user_auth_token_validity_depends_on_used_flag_and_expiry(): void
    {
        $token = new UserAuthToken([
            'used' => false,
            'expires_at' => now()->addMinute(),
        ]);

        $this->assertTrue($token->isValid());
        $this->assertFalse($token->isExpired());

        $token->used = true;
        $this->assertFalse($token->isValid());

        Carbon::setTestNow(now()->addHours(2));
        $expired = new UserAuthToken([
            'used' => false,
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($expired->isValid());
        Carbon::setTestNow();
    }

    public function test_tenant_invitation_pending_state_requires_future_expiry_and_pending_status(): void
    {
        $tenant = Tenant::create([
            'name' => 'Invite Agency',
            'status' => 'active',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);

        $role = Role::create([
            'tenant_id' => null,
            'key' => 'agency_staff',
            'name' => 'Agency Staff',
            'scope' => 'tenant',
            'is_external' => false,
            'description' => 'Agency Staff',
        ]);

        $invitation = TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'email' => 'member@example.com',
            'role_id' => $role->id,
            'token' => 'token_hash',
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $this->assertTrue($invitation->isPending());

        $invitation->forceFill([
            'accepted_at' => now(),
        ])->save();

        $this->assertFalse($invitation->fresh()->isPending());
    }

    public function test_tenant_order_and_user_relationships_are_wired_correctly(): void
    {
        $tenant = Tenant::create([
            'name' => 'Relations Agency',
            'status' => 'active',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);

        $user = User::factory()->create([
            'email' => 'relation@example.com',
        ]);

        $role = Role::create([
            'tenant_id' => null,
            'key' => 'customer',
            'name' => 'Customer',
            'scope' => 'tenant',
            'is_external' => false,
            'description' => 'Customer',
        ]);

        TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'duffel_order_id' => 'ord_relation_1',
            'booking_reference' => 'REL-100',
            'type' => 'instant',
            'status' => Order::STATUS_BOOKED,
            'cancellation_status' => Order::CANCELLATION_STATUS_NONE,
            'refund_status' => null,
            'base_amount' => 100,
            'base_currency' => 'USD',
            'tax_amount' => 15,
            'tax_currency' => 'USD',
            'total_amount' => 115,
            'total_currency' => 'USD',
            'synced_at' => now(),
            'void_window_ends_at' => now()->addDay(),
            'meta' => [],
        ]);

        Passenger::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'type' => 'adult',
            'title' => 'Mr',
            'given_name' => 'Adult',
            'family_name' => 'Traveller',
            'dob' => '1990-01-01',
            'gender' => 'male',
        ]);

        $this->assertTrue($tenant->orders->contains($order));
        $this->assertTrue($order->tenant->is($tenant));
        $this->assertTrue($order->user->is($user));
        $this->assertSame($role->id, $user->tenants->first()->pivot->role_id);
        $this->assertSame($tenant->id, $user->tenants->first()->id);
        $this->assertCount(1, $order->passengers);
        $this->assertTrue($order->passengers->first()->order->is($order));
    }

    public function test_passenger_infant_relationships_work_in_both_directions(): void
    {
        $tenant = Tenant::create([
            'name' => 'Infant Agency',
            'status' => 'active',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'duffel_order_id' => 'ord_infant_1',
            'booking_reference' => 'INF-100',
            'type' => 'instant',
            'status' => Order::STATUS_BOOKED,
            'cancellation_status' => Order::CANCELLATION_STATUS_NONE,
            'refund_status' => null,
            'base_amount' => 100,
            'base_currency' => 'USD',
            'tax_amount' => 15,
            'tax_currency' => 'USD',
            'total_amount' => 115,
            'total_currency' => 'USD',
            'synced_at' => now(),
            'void_window_ends_at' => now()->addDay(),
            'meta' => [],
        ]);

        $adult = Passenger::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'type' => 'adult',
            'title' => 'Ms',
            'given_name' => 'Adult',
            'family_name' => 'Parent',
            'dob' => '1988-01-01',
            'gender' => 'female',
        ]);

        $infant = Passenger::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'infant_passenger_id' => $adult->id,
            'type' => 'infant_without_seat',
            'title' => 'Master',
            'given_name' => 'Baby',
            'family_name' => 'Parent',
            'dob' => '2024-01-01',
            'gender' => 'male',
        ]);

        $this->assertTrue($infant->infant->is($adult));
        $this->assertTrue($adult->infants->contains($infant));
        $this->assertTrue($infant->order->is($order));
    }
}
