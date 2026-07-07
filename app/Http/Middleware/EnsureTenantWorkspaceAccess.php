<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\Role;
use App\Support\RoleCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $role = RoleCatalog::normalize($user->getRole());

        if (RoleCatalog::isPlatformRole($role)) {
            return $next($request);
        }

        $tenant = $this->resolveTenant($request);

        if (! $tenant) {
            abort(422, 'Tenant context is required.');
        }

        if (! in_array($tenant->status, ['active'], true)) {
            abort(403, 'This tenant does not have workspace access.');
        }

        $membership = $user->tenants()
            ->where('tenants.id', $tenant->id)
            ->wherePivotNull('deleted_at')
            ->wherePivot('status', 'active')
            ->first();

        if (! $membership) {
            abort(403, 'You do not have access to this tenant workspace.');
        }

        $membershipRole = Role::query()->whereKey($membership->pivot?->role_id)->value('key');
        if (! RoleCatalog::isCustomerRole($membershipRole)) {
            return $next($request);
        }

        abort(403, 'Customer accounts cannot access workspace routes.');
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $tenantKey = $request->input('tenantKey')
            ?? $request->query('tenantKey')
            ?? $request->route('tenantKey');

        if (is_string($tenantKey) && $tenantKey !== '') {
            return Tenant::where('key', $tenantKey)->first();
        }

        $tenantId = $request->input('tenant_id')
            ?? $request->query('tenant_id')
            ?? $request->route('tenant');

        if (is_numeric($tenantId)) {
            return Tenant::whereKey($tenantId)->first();
        }

        return null;
    }
}
