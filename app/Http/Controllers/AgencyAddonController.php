<?php

namespace App\Http\Controllers;

use App\Models\PlatformAddon;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Support\RoleCatalog;
use Illuminate\Http\Request;

class AgencyAddonController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $this->resolveTenant($request);

        return response()->json([
            'message' => 'Agency add-ons fetched successfully.',
            'data' => $this->loadCatalog($tenant, false),
        ]);
    }

    public function available(Request $request)
    {
        $tenant = $this->resolveTenant($request);

        return response()->json([
            'message' => 'Available booking add-ons fetched successfully.',
            'data' => $this->loadCatalog($tenant, true),
        ]);
    }

    public function update(Request $request, PlatformAddon $addon)
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'display_description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (RoleCatalog::isPlatformRole($user?->getRole()) && array_key_exists('is_active', $validated)) {
            $addon->forceFill([
                'is_active' => (bool) $validated['is_active'],
            ])->save();
        }

        $tenantAddon = TenantAddon::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'addon_id' => $addon->id,
            ],
            [
                'is_enabled' => (bool) $validated['is_enabled'],
                'display_name' => $this->normalizeText($validated['display_name'] ?? null),
                'display_description' => $this->normalizeText($validated['display_description'] ?? null),
                'price' => $this->normalizePrice($validated['price'] ?? 0),
                'currency' => strtoupper((string) $validated['currency']),
            ]
        );

        return response()->json([
            'message' => 'Agency add-on saved successfully.',
            'data' => $this->formatAddon($addon->fresh(), $tenantAddon),
        ]);
    }

    public function reset(Request $request, PlatformAddon $addon)
    {
        $tenant = $this->resolveTenant($request);

        $tenantAddon = TenantAddon::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'addon_id' => $addon->id,
            ],
            [
                'is_enabled' => false,
                'display_name' => null,
                'display_description' => null,
                'price' => 0,
                'currency' => 'LKR',
            ]
        );

        return response()->json([
            'message' => 'Agency add-on reset successfully.',
            'data' => $this->formatAddon($addon, $tenantAddon),
        ]);
    }

    private function loadCatalog(Tenant $tenant, bool $onlyActiveAndEnabled): array
    {
        $platformAddons = PlatformAddon::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $platformAddons
            ->map(function (PlatformAddon $addon) use ($tenant, $onlyActiveAndEnabled) {
                $tenantAddon = $this->resolveTenantAddon($tenant, $addon);
                $payload = $this->formatAddon($addon, $tenantAddon);

                if ($onlyActiveAndEnabled && (! $payload['is_active'] || ! $payload['is_enabled'])) {
                    return null;
                }

                return $payload;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function formatAddon(PlatformAddon $addon, ?TenantAddon $tenantAddon): array
    {
        $displayName = $tenantAddon?->display_name;
        $displayDescription = $tenantAddon?->display_description;

        return [
            'id' => $addon->id,
            'code' => $addon->code,
            'default_name' => $addon->default_name,
            'default_description' => $addon->default_description,
            'category' => $addon->category,
            'is_active' => (bool) $addon->is_active,
            'sort_order' => $addon->sort_order,
            'tenant_addon_id' => $tenantAddon?->id,
            'tenant_id' => $tenantAddon?->tenant_id,
            'is_enabled' => (bool) ($tenantAddon?->is_enabled ?? false),
            'display_name' => $displayName,
            'display_description' => $displayDescription,
            'price' => $this->normalizePrice($tenantAddon?->price ?? 0),
            'currency' => strtoupper((string) ($tenantAddon?->currency ?? 'LKR')),
            'final_name' => $displayName ?: $addon->default_name,
            'final_description' => $displayDescription ?: $addon->default_description,
        ];
    }

    private function resolveTenant(Request $request): Tenant
    {
        $tenantKey = $request->input('tenantKey')
            ?? $request->query('tenantKey')
            ?? $request->route('tenantKey');

        if (is_string($tenantKey) && trim($tenantKey) !== '') {
            $tenant = Tenant::where('key', trim($tenantKey))->first();

            if ($tenant) {
                return $tenant;
            }
        }

        $tenant = $request->user()?->primaryTenant();

        if ($tenant) {
            return $tenant;
        }

        abort(422, 'Tenant context is required.');
    }

    private function resolveTenantAddon(Tenant $tenant, PlatformAddon $addon): TenantAddon
    {
        return TenantAddon::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'addon_id' => $addon->id,
            ],
            [
                'is_enabled' => false,
                'display_name' => null,
                'display_description' => null,
                'price' => 0,
                'currency' => 'LKR',
            ]
        );
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizePrice(mixed $value): string
    {
        if ($value === null || $value === '') {
            return number_format(0, 2, '.', '');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
