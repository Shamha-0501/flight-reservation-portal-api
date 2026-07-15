<?php

namespace Tests\Feature;

use App\Jobs\SendBladeMail;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserAuthToken;
use App\Services\ActivityLogger;
use App\Services\CurrencyConverter;
use App\Services\Duffel\DuffelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class BackendWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_index_filters_by_tenant_search_and_status(): void
    {
        $this->bindCommonServices();

        $tenant = $this->createTenant('active');
        $otherTenant = $this->createTenant('active');

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $matching = $this->createOrder($tenant, [
            'booking_reference' => 'REF-100',
            'status' => Order::STATUS_BOOKED,
            'user_id' => $user->id,
        ]);
        Passenger::create([
            'tenant_id' => $tenant->id,
            'order_id' => $matching->id,
            'type' => 'adult',
            'title' => 'Mr',
            'given_name' => 'Amin',
            'family_name' => 'Khan',
            'dob' => '1990-01-01',
            'gender' => 'male',
        ]);

        $this->createOrder($tenant, [
            'booking_reference' => 'REF-200',
            'status' => Order::STATUS_CANCELLED,
            'cancellation_status' => Order::CANCELLATION_STATUS_CANCELLED,
            'refund_status' => Order::REFUND_STATUS_PENDING,
            'user_id' => $otherUser->id,
        ]);

        $this->createOrder($otherTenant, [
            'booking_reference' => 'REF-100',
            'status' => Order::STATUS_BOOKED,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/bookings?tenantKey=' . $tenant->key . '&search=REF-100&status=' . urlencode(Order::STATUS_BOOKED));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.booking_reference', 'REF-100');
        $response->assertJsonPath('data.0.status', Order::STATUS_BOOKED);
    }

    public function test_order_show_returns_order_for_matching_tenant(): void
    {
        $this->bindCommonServices();

        $tenant = $this->createTenant('active');
        $order = $this->createOrder($tenant, [
            'booking_reference' => 'BOOK-123',
            'status' => Order::STATUS_BOOKED,
        ]);

        Passenger::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'type' => 'adult',
            'title' => 'Ms',
            'given_name' => 'Nila',
            'family_name' => 'Perera',
            'dob' => '1992-02-02',
            'gender' => 'female',
        ]);

        $this->getJson('/api/bookings/' . $order->id . '?tenantKey=' . $tenant->key)
            ->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.booking_reference', 'BOOK-123')
            ->assertJsonPath('order.status', Order::STATUS_BOOKED);
    }

    public function test_admin_can_approve_tenant_and_dispatch_approval_mail(): void
    {
        $this->bindCommonServices();
        Bus::fake();

        [$admin] = $this->createTenantMember('system_developer');
        $creator = User::factory()->create();
        $tenant = $this->createTenant('pending', $creator->id);

        $this->actingAs($admin);

        $this->postJson('/api/admin/tenants/' . $tenant->id . '/approve')
            ->assertOk()
            ->assertJsonPath('tenant.status', 'active');

        Bus::assertDispatched(SendBladeMail::class);
    }

    public function test_admin_can_reject_tenant_and_dispatch_rejection_mail(): void
    {
        $this->bindCommonServices();
        Bus::fake();

        [$admin] = $this->createTenantMember('system_developer');
        $creator = User::factory()->create();
        $tenant = $this->createTenant('pending', $creator->id);

        $this->actingAs($admin);

        $this->postJson('/api/admin/tenants/' . $tenant->id . '/reject')
            ->assertOk()
            ->assertJsonPath('tenant.status', 'rejected');

        Bus::assertDispatched(SendBladeMail::class);
    }

    public function test_admin_can_suspend_and_reactivate_tenant(): void
    {
        $this->bindCommonServices();

        [$admin] = $this->createTenantMember('system_developer');
        $tenant = $this->createTenant('active');

        $this->actingAs($admin);

        $this->postJson('/api/admin/tenants/' . $tenant->id . '/suspend')
            ->assertOk()
            ->assertJsonPath('tenant.status', 'suspended');

        $this->postJson('/api/admin/tenants/' . $tenant->id . '/reactivate')
            ->assertOk()
            ->assertJsonPath('tenant.status', 'active');
    }

    public function test_tenant_owner_can_invite_member_and_invited_user_can_accept(): void
    {
        $this->bindCommonServices();
        Bus::fake();

        [$owner, $tenant] = $this->createTenantMember('tenant_owner');
        $role = Role::query()->where('key', 'agency_staff')->firstOrFail();
        $invitee = User::factory()->create([
            'email' => 'agent@example.com',
            'name' => 'Agent User',
        ]);

        $this->actingAs($owner);

        $inviteResponse = $this->postJson('/api/tenants/members/invite', [
            'tenantKey' => $tenant->key,
            'email' => $invitee->email,
            'role_key' => $role->key,
        ]);

        $inviteResponse->assertStatus(201);
        $inviteUrl = $inviteResponse->json('invite_url');
        $this->assertIsString($inviteUrl);

        parse_str(parse_url($inviteUrl, PHP_URL_QUERY) ?? '', $query);
        $token = $query['token'] ?? null;
        $this->assertIsString($token);

        $this->actingAs($invitee);

        $this->postJson('/api/tenant-invitations/accept', [
            'token' => $token,
        ])
            ->assertOk()
            ->assertJsonPath('member.status', 'active')
            ->assertJsonPath('member.role_key', 'agency_staff');

        Bus::assertDispatched(SendBladeMail::class);

        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $invitee->id,
            'status' => 'active',
        ]);
    }

    public function test_email_verification_with_code_marks_user_verified(): void
    {
        $this->bindCommonServices();
        $this->withoutMiddleware();

        $user = User::factory()->create([
            'email' => 'verify@example.com',
            'account_state' => 'order_verified_only',
            'email_verified_at' => null,
        ]);

        $code = '12345678';
        UserAuthToken::create([
            'user_id' => $user->id,
            'type' => 'email_verification',
            'token' => Hash::make($code),
            'used' => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/auth/verify-email-code', [
            'email' => $user->email,
            'code' => $code,
        ])
            ->assertOk()
            ->assertJsonPath('verified', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('user_auth_tokens', [
            'user_id' => $user->id,
            'type' => 'email_verification',
            'used' => 1,
        ]);
    }

    public function test_register_route_creates_customer_link_and_returns_api_payload(): void
    {
        $this->bindCommonServices();
        Bus::fake();

        $this->seedRoleSet();
        $tenant = $this->createTenant('active');

        $response = $this->postJson('/auth/register', [
            'tenantKey' => $tenant->key,
            'name' => 'New Customer',
            'email' => 'new.customer@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user.email', 'new.customer@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'new.customer@example.com',
            'name' => 'New Customer',
        ]);

        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);
    }

    public function test_register_route_rejects_duplicate_email_with_json_error(): void
    {
        $this->bindCommonServices();

        $this->seedRoleSet();
        $tenant = $this->createTenant('active');
        User::factory()->create([
            'email' => 'duplicate@example.com',
            'name' => 'Existing User',
        ]);

        $this->postJson('/auth/register', [
            'tenantKey' => $tenant->key,
            'name' => 'New Customer',
            'email' => 'duplicate@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'This email is already registered.');
    }

    public function test_login_route_returns_authenticated_user_and_rejects_bad_credentials(): void
    {
        $this->bindCommonServices();

        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $this->postJson('/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user.email', $user->email);

        $this->postJson('/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('errors.email.0', 'Invalid credentials.');
    }

    public function test_booking_read_only_endpoints_use_mocked_duffel_service(): void
    {
        $this->bindCommonServices();

        $duffel = $this->mock(DuffelService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getPlaceSuggestions')
                ->once()
                ->with('lon')
                ->andReturn(['data' => [['id' => 'LON', 'name' => 'London']]]);

            $mock->shouldReceive('searchFlights')
                ->once()
                ->andReturn(['data' => ['id' => 'offer_request_1']]);

            $mock->shouldReceive('getSeatMaps')
                ->once()
                ->with('offer_1')
                ->andReturn(['data' => [['seat_map_id' => 'seat_1']]]);
        });

        $this->getJson('/api/places?q=lon')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'LON');

        $this->postJson('/api/flights/search', [
            'originLocationCode' => 'CMB',
            'destinationLocationCode' => 'DXB',
            'departureDate' => now()->addDay()->toDateString(),
            'adults' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('offer_request_id', 'offer_request_1');

        $this->getJson('/api/seat-maps?offer_id=offer_1')
            ->assertOk()
            ->assertJsonPath('data.0.seat_map_id', 'seat_1');

        $this->assertNotNull($duffel);
    }

    public function test_booking_creation_validation_returns_json_errors(): void
    {
        $this->bindCommonServices();

        $this->mock(DuffelService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('getOffer');
            $mock->shouldNotReceive('createOrder');
        });

        $this->postJson('/api/orders', [
            'tenantKey' => '',
            'offer_id' => '',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Validation failed')
            ->assertJsonStructure([
                'messages' => [
                    'tenantKey',
                    'offer_id',
                    'passengers',
                ],
            ]);
    }

    public function test_booking_creation_returns_successful_api_response(): void
    {
        $this->bindCommonServices();

        $this->seedRoleSet();
        $tenant = $this->createTenant('active');
        $customer = User::factory()->create([
            'email' => 'buyer@example.com',
            'name' => 'Buyer User',
        ]);

        \Mockery::mock('alias:App\Services\MailService')
            ->shouldReceive('sendMail')
            ->once()
            ->andReturn([
                'ok' => true,
                'message' => 'Mail sent successfully',
            ]);

        $this->mock(DuffelService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getOffer')
                ->once()
                ->with('offer_123')
                ->andReturn([
                    'data' => [
                        'id' => 'offer_123',
                        'base_amount' => '100.00',
                        'base_currency' => 'USD',
                        'tax_amount' => '15.00',
                        'tax_currency' => 'USD',
                        'total_amount' => '115.00',
                        'total_currency' => 'USD',
                    ],
                ]);

            $mock->shouldReceive('createOrder')
                ->once()
                ->andReturn([
                    'data' => [
                        'id' => 'duffel_order_123',
                        'booking_reference' => 'BR-123456',
                        'type' => 'instant',
                        'base_amount' => '100.00',
                        'base_currency' => 'USD',
                        'tax_amount' => '15.00',
                        'tax_currency' => 'USD',
                        'total_amount' => '115.00',
                        'total_currency' => 'USD',
                        'void_window_ends_at' => now()->addDay()->toISOString(),
                    ],
                ]);
        });

        $response = $this->postJson('/api/orders', [
            'tenantKey' => $tenant->key,
            'offer_id' => 'offer_123',
            'contact_email' => $customer->email,
            'passengers' => [
                [
                    'id' => 'pax_1',
                    'type' => 'adult',
                    'title' => 'Mr',
                    'given_name' => 'Buyer',
                    'family_name' => 'User',
                    'born_on' => '1990-01-01',
                    'gender' => 'male',
                    'email' => $customer->email,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Order created successfully')
            ->assertJsonPath('order.booking_reference', 'BR-123456')
            ->assertJsonPath('order.status', Order::STATUS_BOOKED)
            ->assertJsonPath('order.passengers.0.given_name', 'Buyer');

        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'booking_reference' => 'BR-123456',
            'status' => Order::STATUS_BOOKED,
        ]);
    }

    public function test_finalized_cancellation_and_change_workflows_return_failure_responses(): void
    {
        $this->bindCommonServices();

        $tenant = $this->createTenant('active');
        $order = $this->createOrder($tenant, [
            'status' => Order::STATUS_RESCHEDULED,
            'meta' => [
                'change' => [
                    'status' => 'confirmed',
                ],
                'cancellation' => [
                    'status' => 'confirmed',
                ],
            ],
        ]);

        $this->postJson('/api/orders/' . $order->id . '/change/approve', [
            'tenantKey' => $tenant->key,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Change workflow is already finalized');

        $this->postJson('/api/orders/' . $order->id . '/cancellation/reject', [
            'tenantKey' => $tenant->key,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Cancellation workflow is already finalized');
    }

    public function test_refund_and_change_status_endpoints_return_stored_conditions(): void
    {
        $this->bindCommonServices();

        $tenant = $this->createTenant('active');
        $order = $this->createOrder($tenant, [
            'status' => Order::STATUS_BOOKED,
            'meta' => [
                'duffel_order' => [
                    'conditions' => [
                        'refund_before_departure' => [
                            'allowed' => true,
                            'penalty_amount' => '20.00',
                            'penalty_currency' => 'USD',
                        ],
                        'change_before_departure' => [
                            'allowed' => true,
                            'penalty_amount' => '15.00',
                            'penalty_currency' => 'USD',
                        ],
                    ],
                ],
            ],
        ]);

        $this->getJson('/api/order-refundable-status/' . $order->id)
            ->assertOk()
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('penalty_amount', '20.00');

        $this->getJson('/api/order-changeable-status/' . $order->id)
            ->assertOk()
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('penalty_amount', '15.00');
    }

    public function test_refund_status_endpoint_returns_false_when_refund_is_disallowed(): void
    {
        $this->bindCommonServices();

        $tenant = $this->createTenant('active');
        $order = $this->createOrder($tenant, [
            'meta' => [
                'duffel_order' => [
                    'conditions' => [
                        'refund_before_departure' => [
                            'allowed' => false,
                            'penalty_amount' => '0.00',
                            'penalty_currency' => 'USD',
                        ],
                    ],
                ],
            ],
        ]);

        $this->getJson('/api/order-refundable-status/' . $order->id)
            ->assertOk()
            ->assertJsonPath('allowed', false)
            ->assertJsonPath('penalty_amount', '0.00');
    }

    public function test_cancellation_flow_updates_order_and_uses_mocked_duffel_service(): void
    {
        $this->bindCommonServices();
        Bus::fake();

        $tenant = $this->createTenant('active');
        $customer = User::factory()->create([
            'email' => 'cancel@example.com',
        ]);
        $order = $this->createOrder($tenant, [
            'user_id' => $customer->id,
            'status' => Order::STATUS_BOOKED,
            'cancellation_status' => Order::CANCELLATION_STATUS_NONE,
            'refund_status' => null,
            'meta' => [
                'duffel_order' => [
                    'conditions' => [
                        'refund_before_departure' => [
                            'allowed' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $this->mock(DuffelService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createOrderCancellation')
                ->once()
                ->andReturn([
                    'data' => [
                        'id' => 'can_1',
                        'refund_amount' => '45.00',
                        'refund_currency' => 'USD',
                        'cancellation_fee' => '5.00',
                        'cancellation_fee_currency' => 'USD',
                        'expires_at' => now()->addHour()->toISOString(),
                        'warnings' => [],
                    ],
                ]);

            $mock->shouldReceive('getOrderCancellation')
                ->once()
                ->andReturn([
                    'data' => [
                        'id' => 'can_1',
                        'refund_amount' => '45.00',
                        'refund_currency' => 'USD',
                        'cancellation_fee' => '5.00',
                        'cancellation_fee_currency' => 'USD',
                        'expires_at' => now()->addHour()->toISOString(),
                        'warnings' => [],
                    ],
                ]);

            $mock->shouldReceive('confirmOrderCancellation')
                ->once()
                ->andReturn([
                    'data' => [
                        'id' => 'can_1',
                        'refund_amount' => '45.00',
                        'refund_currency' => 'USD',
                        'cancellation_fee' => '5.00',
                        'cancellation_fee_currency' => 'USD',
                    ],
                ]);
        });

        $this->postJson('/api/order-cancellations', [
            'order_id' => (string) $order->id,
            'tenantKey' => $tenant->key,
        ])
            ->assertOk()
            ->assertJsonPath('quote.cancellation_id', 'can_1');

        $this->postJson('/api/order-cancellations/can_1/confirm/' . $order->id, [
            'tenantKey' => $tenant->key,
        ])
            ->assertOk()
            ->assertJsonPath('status', Order::STATUS_CANCELLED);

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        Bus::assertDispatched(SendBladeMail::class);
    }

    public function test_change_flow_updates_order_and_uses_mocked_duffel_service(): void
    {
        $this->bindCommonServices();
        Bus::fake();

        $tenant = $this->createTenant('active');
        $customer = User::factory()->create([
            'email' => 'change@example.com',
        ]);
        $order = $this->createOrder($tenant, [
            'user_id' => $customer->id,
            'status' => Order::STATUS_BOOKED,
            'meta' => [
                'duffel_order' => [
                    'slices' => [
                        [
                            'id' => 'slice_1',
                            'origin' => ['iata_code' => 'CMB'],
                            'destination' => ['iata_code' => 'DXB'],
                            'departing_at' => now()->addDay()->toISOString(),
                            'cabin_class' => 'economy',
                        ],
                    ],
                ],
            ],
        ]);

        \Mockery::mock('alias:App\Services\MailService')
            ->shouldReceive('sendMail')
            ->once()
            ->andReturn([
                'ok' => true,
                'message' => 'Mail sent successfully',
            ]);

        $this->mock(DuffelService::class, function (MockInterface $mock) use ($order) {
            $mock->shouldReceive('createOrderChangeRequest')
                ->once()
                ->andReturn([
                    'data' => ['id' => 'change_request_1'],
                    'offers' => [
                        ['id' => 'offer_a'],
                    ],
                ]);

            $mock->shouldReceive('createOrderChange')
                ->once()
                ->andReturn([
                    'data' => [
                        'id' => 'order_change_1',
                        'order_id' => $order->duffel_order_id,
                    ],
                ]);

            $mock->shouldReceive('getOrder')
                ->once()
                ->andReturn([
                    'data' => [
                        'id' => $order->duffel_order_id,
                        'status' => 'confirmed',
                    ],
                ]);

            $mock->shouldReceive('confirmOrderChange')
                ->once()
                ->andReturn([
                    'data' => [
                        'id' => 'order_change_1',
                        'order_id' => $order->duffel_order_id,
                    ],
                ]);
        });

        $this->postJson('/api/order-change-requests', [
            'order_id' => (string) $order->id,
            'slices' => [
                'remove' => [
                    ['slice_id' => 'slice_1'],
                ],
                'add' => [
                    [
                        'origin' => 'CMB',
                        'destination' => 'DXB',
                        'departure_date' => now()->addDay()->toDateString(),
                        'cabin_class' => 'economy',
                    ],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.id', 'change_request_1');

        $this->postJson('/api/order-changes', [
            'selected_order_change_offer' => 'offer_a',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', 'order_change_1');

        $response = $this->postJson('/api/order-changes/order_change_1/confirm', [
            'payment' => [
                'type' => 'balance',
                'amount' => '10.00',
                'currency' => 'USD',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('change_status', 'confirmed')
            ->assertJsonPath('status', Order::STATUS_RESCHEDULED);

        Bus::assertDispatched(SendBladeMail::class);
    }

    private function bindCommonServices(): void
    {
        $this->mock(CurrencyConverter::class, function (MockInterface $mock) {
            $mock->shouldReceive('defaultCurrency')->andReturn('USD')->byDefault();
            $mock->shouldReceive('convertAmount')->andReturnUsing(
                fn ($amount, ...$rest) => $amount === null || $amount === '' ? null : number_format((float) $amount, 2, '.', '')
            )->byDefault();
            $mock->shouldReceive('convertPayload')->andReturnUsing(
                fn ($value) => $value
            )->byDefault();
        });

        $this->mock(ActivityLogger::class, function (MockInterface $mock) {
            $mock->shouldReceive('log')->andReturn(new ActivityLog())->byDefault();
        });
    }

    private function createTenant(string $status = 'active', ?int $createdByUserId = null): Tenant
    {
        return Tenant::create([
            'name' => fake()->company(),
            'status' => $status,
            'timezone' => 'UTC',
            'locale' => 'en',
            'created_by_user_id' => $createdByUserId,
        ]);
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function createTenantMember(string $roleKey, string $tenantStatus = 'active', ?Tenant $tenant = null): array
    {
        $this->seedRoleSet();

        $tenant ??= $this->createTenant($tenantStatus);
        $user = User::factory()->create();
        $role = Role::query()->where('key', $roleKey)->firstOrFail();

        TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $tenant];
    }

    private function createOrder(Tenant $tenant, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'duffel_order_id' => 'ord_' . Str::lower(Str::random(10)),
            'booking_reference' => 'BR-' . Str::upper(Str::random(6)),
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
        ], $overrides));
    }

    private function seedRoleSet(): void
    {
        foreach ([
            ['system_developer', 'System Developer', 'global'],
            ['super_admin', 'Super Admin', 'global'],
            ['tenant_owner', 'Tenant Owner', 'tenant'],
            ['tenant_admin', 'Tenant Admin', 'tenant'],
            ['agency_manager', 'Agency Manager', 'tenant'],
            ['agency_staff', 'Agency Staff', 'tenant'],
            ['customer', 'Customer', 'tenant'],
        ] as [$key, $name, $scope]) {
            Role::query()->updateOrCreate(
                ['tenant_id' => null, 'key' => $key],
                [
                    'name' => $name,
                    'scope' => $scope,
                    'is_external' => false,
                    'description' => $name,
                ]
            );
        }
    }
}
