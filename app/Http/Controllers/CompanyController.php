<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantBranding;
use App\Models\TenantSetting;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function bootstrap(Request $request)
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string', 'exists:tenants,key'],
        ]);

        $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();
        $branding = TenantBranding::where('tenant_id', $tenant->id)->first();
        $settings = TenantSetting::where('tenant_id', $tenant->id)->first();
        $settingsData = $settings?->settings;

        if (is_string($settingsData)) {
            $settingsData = json_decode($settingsData, true);
        }

        if (! is_array($settingsData)) {
            $settingsData = [];
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'tenant' => [
                    'key' => $tenant->key,
                    'name' => $tenant->name,
                ],
                'theme' => [
                    'mode_default' => $settingsData['mode_default'] ?? 'light',
                    'tokens' => [
                        'light' => [
                            'primary' => $branding?->primary_color ?? '#2563eb',
                            'secondary' => $branding?->secondary_color ?? '#0f172a',
                            'accent' => $branding?->accent_color ?? '#10b981',
                            'brand_name' => $branding?->brand_name ?? $tenant->name,
                            'site_title' => $branding?->site_title ?? $tenant->name,
                        ],
                        'dark' => [
                            'primary' => $branding?->primary_color ?? '#60a5fa',
                            'secondary' => $branding?->secondary_color ?? '#e2e8f0',
                            'accent' => $branding?->accent_color ?? '#34d399',
                            'brand_name' => $branding?->brand_name ?? $tenant->name,
                            'site_title' => $branding?->site_title ?? $tenant->name,
                        ],
                    ],
                    'custom_css' => $settingsData['custom_css'] ?? null,
                ],
            ],
        ]);
    }
}
