<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Services\Pbac\PbacEvaluator;
use App\Services\Pbac\PbacPolicyLoader;
use App\Support\RoleCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'account_state',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user', 'user_id', 'tenant_id')
            ->using(TenantUser::class)
            ->withPivot([
                'role_id',
                'invited_by_user_id',
                'status',
                'deleted_at',
                'created_at',
                'updated_at',
            ])
            ->withTimestamps();
    }

    /**
     * Get user tenant using tenant key
     * @param string $tenantKey
     * 
     * @return Tenant
     */
    public function getTenantByKey(string $tenantKey): ?Tenant
    {
        return Tenant::query()
            ->where('key', $tenantKey)
            ->whereHas('users', function ($q) {
                $q->where('tenant_user.user_id', $this->id)
                    ->whereNull('tenant_user.deleted_at')
                    ->where('tenant_user.status', 'active');
            })
            ->first();
    }

    public function hasAnyRole(array $roles): bool
    {
        $roles = array_values(array_filter(array_map(
            static fn ($role) => RoleCatalog::normalize(is_string($role) ? $role : null),
            $roles
        )));

        if ($roles === []) {
            return false;
        }

        return $this->tenants()
            ->whereNull('tenant_user.deleted_at')
            ->wherePivot('status', 'active')
            ->get()
            ->contains(function (Tenant $tenant) use ($roles) {
                $roleKey = Role::query()
                    ->whereKey($tenant->pivot?->role_id)
                    ->value('key');

                return $roleKey !== null && in_array($roleKey, $roles, true);
            });
    }

    public function primaryTenant(): ?Tenant
    {
        $tenants = $this->tenants()
            ->whereNull('tenant_user.deleted_at')
            ->wherePivot('status', 'active')
            ->get();

        if ($tenants->isEmpty()) {
            return null;
        }

        return $tenants->sortBy(fn (Tenant $tenant) => RoleCatalog::membershipPriority(
            Role::query()->whereKey($tenant->pivot?->role_id)->value('key'),
            $tenant->status
        ))->first();
    }

    /**
     * Get user role from pbac_user_role resource
     * 
     * @return string
     */
    public function getRole()
    {
        return $this->primaryRoleKey();
    }

    public function primaryRoleKey(): ?string
    {
        $roles = $this->tenants()
            ->whereNull('tenant_user.deleted_at')
            ->get()
            ->map(function (Tenant $tenant) {
                return [
                    'role' => Role::query()->whereKey($tenant->pivot?->role_id)->value('key'),
                    'status' => $tenant->status,
                ];
            })
            ->sortBy(fn (array $membership) => RoleCatalog::membershipPriority($membership['role'], $membership['status']))
            ->values();

        return $roles->first()['role'] ?? null;
    }

    /**
     * Check if user is admin for prevent from normal users
     * access admin panel while log in.
     * 
     * @return bool
     */
    public function canAccessAdminPortal()
    {
        return RoleCatalog::isPlatformRole($this->getRole());
    }

    /**
     * Check if bypass role 
     * 
     * @return bool
     */
    public function isBypassRole()
    {
        return $this->canAccessAdminPortal();
    }

    /**
     * Get permission for given @var Resource, @var Action
     * 
     * @return ?Permission
     */
    public function findPermission(Resource $resource, Action $action): ?Permission
    {
        $key = "$action->action:$resource->resource";
        return Permission::where('key', $key)->first() ?? null;
    }

    /**
     * Get all active role ids from tenant / global
     * 
     * @return array<integer>
     */
    public function getActiveRoleIds(?int $tenantId) 
    {
        return $this->tenants()
            ->whereNull('tenant_user.deleted_at')
            ->when(
                $tenantId,
                fn($q) => $q->where('tenants.id', $tenantId)
            )
            ->pluck('tenant_user.role_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Evaluate access using @var Resource, @var Action, @var Model, @var env
     * 
     * @return bool
     */
    public function canAccess(Resource $resource, Action $action, ?Model $resourceObject = null, array $env = []): bool
    {
        // 1) Developer bypass (optional but recommended)
        if ($this->isBypassRole()) return true;

        // 2) Resolve permission
        $permission = $this->findPermission($resource, $action);
        if (!$permission) return false;

        // 4) Load tenant
        $tenant = $this->getTenantByKey($env['tenant_key'] ?? '');
        $tenantId = $tenant?->id;

        // 3) Get active roles for this tenant / global(if user not in tenant context)
        $roleIds = $this->getActiveRoleIds($tenantId);
        
        // TODO: Remove after dubug (only for debug purpose)
        $env['role_ids'] = $roleIds;
        $env['permission_key'] = $permission->key;

        // 5) Load candidate policies (tenant / global  + by user / role + by permission)
        $policies = app(PbacPolicyLoader::class)->load(
            tenantId:  $tenant ? $tenant->id : null,
            userId: $this->id,
            roleIds: $roleIds,
            permissionId: $permission->id 
        );

        if (empty($policies)) {
            return false;
        }

        return app(PbacEvaluator::class)->evaluate(
            policies: $policies,
            subject: $this,
            resource: $resourceObject,
            env: $env
        );
    }
}
