<?php

namespace App\Http\Controllers;

use App\Models\AgencyMarkupSetting;
use App\Models\Tenant;
use App\Support\RoleCatalog;
use Illuminate\Http\Request;

class AgencyMarkupController extends Controller
{
    public function index(Request $request)
    {
        $this->assertCanManageMarkup($request);
        $tenant = $this->resolveTenant($request);
        $settings = AgencyMarkupSetting::where('tenant_id', $tenant->id)->first();

        return response()->json([
            'message' => 'Agency markup settings fetched successfully.',
            'data' => $this->formatSettings($tenant, $settings),
        ]);
    }

    public function booking(Request $request)
    {
        $tenant = $this->resolveTenant($request);
        $settings = AgencyMarkupSetting::where('tenant_id', $tenant->id)->first();

        return response()->json([
            'message' => 'Agency markup settings fetched successfully.',
            'data' => $this->formatSettings($tenant, $settings),
        ]);
    }

    public function update(Request $request)
    {
        $this->assertCanManageMarkup($request);
        $tenant = $this->resolveTenant($request);

        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'markup_mode' => ['required', 'in:percentage,fixed'],
            'markup_value' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'display_label' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = AgencyMarkupSetting::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'is_enabled' => (bool) $validated['is_enabled'],
                'markup_mode' => $validated['markup_mode'],
                'markup_value' => number_format((float) $validated['markup_value'], 2, '.', ''),
                'currency' => strtoupper($validated['currency']),
                'display_label' => $this->normalizeText($validated['display_label'] ?? null),
            ]
        );

        return response()->json([
            'message' => 'Agency markup settings saved successfully.',
            'data' => $this->formatSettings($tenant, $settings),
        ]);
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

        abort_if(! $tenant, 422, 'Tenant context is required.');

        return $tenant;
    }

    private function assertCanManageMarkup(Request $request): void
    {
        $role = $request->user()?->getRole();

        if (
            RoleCatalog::isPlatformRole($role)
            || $role === 'tenant_owner'
        ) {
            return;
        }

        abort(403, 'You do not have access to this resource.');
    }

    private function formatSettings(Tenant $tenant, ?AgencyMarkupSetting $settings): array
    {
        $markupMode = $settings?->markup_mode === 'fixed' ? 'fixed' : 'percentage';
        $markupValue = $settings?->markup_value !== null
            ? (float) $settings->markup_value
            : 0;

        return [
            'tenant_id' => $tenant->id,
            'is_enabled' => (bool) ($settings?->is_enabled ?? false),
            'markup_mode' => $markupMode,
            'markup_value' => $markupValue,
            'currency' => strtoupper((string) ($settings?->currency ?? 'LKR')),
            'display_label' => $this->normalizeText($settings?->display_label),
            'final_label' => $this->normalizeText($settings?->display_label) ?: 'Agency markup',
        ];
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
