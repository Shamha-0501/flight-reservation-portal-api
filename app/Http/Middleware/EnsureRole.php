<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\Tenant;
use App\Support\RoleCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $allowed = array_values(array_filter(array_map(
            static fn (string $role) => RoleCatalog::normalize($role),
            $roles
        )));

        // If the route is tenant-scoped, check the user's role inside that tenant first.
        $tenant = $this->resolveTenant($request);

        if ($tenant) {
            $membership = $user->tenants()
                ->where('tenants.id', $tenant->id)
                ->whereNull('tenant_user.deleted_at')
                ->wherePivot('status', 'active')
                ->first();

            $membershipRole = $membership
                ? Role::query()->whereKey($membership->pivot?->role_id)->value('key')
                : null;

            if (RoleCatalog::normalize($membershipRole) !== null && in_array(RoleCatalog::normalize($membershipRole), $allowed, true)) {
                return $next($request);
            }

            abort(403, 'You do not have access to this resource.');
        }

        // Fallback for routes that are not tied to a specific tenant context.
        $currentRole = RoleCatalog::normalize($user->getRole());

        if ($currentRole !== null && in_array($currentRole, $allowed, true)) {
            return $next($request);
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($allowed)) {
            return $next($request);
        }

        abort(403, 'You do not have access to this resource.');
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        // Support both query-string and route-parameter tenant lookups.
        $tenantKey = $request->input('tenantKey') ?? $request->query('tenantKey') ?? $request->route('tenantKey');

        if (is_string($tenantKey) && $tenantKey !== '') {
            return Tenant::where('key', $tenantKey)->first();
        }

        $tenantId = $request->input('tenant_id') ?? $request->query('tenant_id') ?? $request->route('tenant');

        if (is_numeric($tenantId)) {
            return Tenant::whereKey($tenantId)->first();
        }

        return null;
    }
}
