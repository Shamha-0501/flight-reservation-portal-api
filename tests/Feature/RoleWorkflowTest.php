<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_user_tenants_with_expected_shape(): void
    {
        [$user, $tenant] = $this->createTenantMember('tenant_owner');

        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.tenants.0.id', $tenant->id)
            ->assertJsonPath('user.tenants.0.key', $tenant->key)
            ->assertJsonPath('user.tenants.0.name', $tenant->name)
            ->assertJsonPath('user.tenants.0.role_key', 'tenant_owner')
            ->assertJsonPath('user.tenants.0.status', 'active');
    }

    public function test_company_bootstrap_returns_tenant_theme_payload(): void
    {
        $tenant = $this->createTenant(status: 'active');

        $this->getJson('/api/company/bootstrap?tenantKey=' . $tenant->key)
            ->assertOk()
            ->assertJsonPath('data.tenant.key', $tenant->key)
            ->assertJsonPath('data.tenant.name', $tenant->name)
            ->assertJsonPath('data.theme.mode_default', 'light')
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'tenant' => ['key', 'name'],
                    'theme' => ['mode_default', 'tokens', 'custom_css'],
                ],
            ]);
    }

    public function test_platform_admin_can_list_pending_tenants(): void
    {
        [$user] = $this->createTenantMember('system_developer');
        $pendingTenant = $this->createTenant(status: 'pending');

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/tenants/pending')
            ->assertOk()
            ->assertJsonFragment(['id' => $pendingTenant->id, 'status' => 'pending']);
    }

    public function test_suspended_tenant_is_blocked_from_workspace_routes(): void
    {
        [$user, $tenant] = $this->createTenantMember('agency_manager', 'suspended');

        Sanctum::actingAs($user);

        $this->getJson('/api/extras?tenantKey=' . $tenant->key)
            ->assertStatus(403);
    }

    public function test_tenant_owner_can_change_member_role(): void
    {
        [$owner, $tenant] = $this->createTenantMember('tenant_owner');
        $member = $this->createTenantMember('agency_staff', 'active', $tenant)[0];
        $membership = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        Sanctum::actingAs($owner);

        $this->patchJson('/api/tenants/members/' . $membership->id . '/role', [
            'tenantKey' => $tenant->key,
            'role_key' => 'agency_manager',
        ])
            ->assertOk()
            ->assertJsonPath('member.role_key', 'agency_manager');
    }

    private function createTenant(string $status = 'active'): Tenant
    {
        $tenant = Tenant::create([
            'name' => fake()->company(),
            'status' => $status,
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);

        return $tenant;
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function createTenantMember(string $roleKey, string $tenantStatus = 'active', ?Tenant $tenant = null): array
    {
        $this->seedRoleSet();

        $tenant ??= $this->createTenant($tenantStatus);
        $user = User::factory()->create();
        $role = Role::where('key', $roleKey)->firstOrFail();

        TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $tenant];
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
