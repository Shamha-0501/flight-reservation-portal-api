<?php

namespace App\Http\Resources;

use App\Models\Role;
use App\Support\RoleCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tenants = $this->whenLoaded('tenants', function () {
            $roleIds = $this->tenants
                ->pluck('pivot.role_id')
                ->filter()
                ->unique()
                ->values();

            $rolesById = Role::query()
                ->whereIn('id', $roleIds)
                ->get()
                ->keyBy('id');

            $sortedTenants = $this->tenants->sortBy(function ($tenant) use ($rolesById) {
                $role = $rolesById->get($tenant->pivot?->role_id);

                return RoleCatalog::membershipPriority(
                    $role?->key ?? 'customer',
                    $tenant->status
                );
            })->values();

            return $sortedTenants->map(function ($tenant) use ($rolesById) {
                $role = $rolesById->get($tenant->pivot?->role_id);
                $roleKey = $role?->key ?? 'customer';

                return [
                    'id' => $tenant->id,
                    'key' => $tenant->key,
                    'name' => $tenant->name,
                    'role' => $role?->name ?? RoleCatalog::label($roleKey),
                    'role_key' => $roleKey,
                    'status' => $tenant->status,
                ];
            })->values();
        }, []);

        $primaryTenant = collect($tenants)->first();

        $primaryRole = $this->getRole();

        $hasNoTenants = $this->relationLoaded('tenants')
            ? $this->tenants->isEmpty()
            : true;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,

            'role' => $primaryRole ?? ($hasNoTenants ? 'customer' : null),
            'role_key' => RoleCatalog::normalize($primaryRole) ?? 'customer',

            'tenant_id' => $primaryTenant['id'] ?? null,
            'tenant_key' => $primaryTenant['key'] ?? null,

            'tenants' => $tenants,
        ];
    }
}