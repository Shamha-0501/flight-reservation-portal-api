<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function platform(Request $request)
    {
        return response()->json([
            'ok' => true,
            'data' => $this->normalizeSettings('platform', $this->loadPlatformSettings()),
        ]);
    }

    public function updatePlatform(Request $request)
    {
        $validated = $this->validateSettings($request, 'platform');
        $record = PlatformSetting::firstOrNew(['key' => 'default']);
        $record->settings = $this->extractSettings($validated);
        $record->save();

        return response()->json([
            'ok' => true,
            'data' => $this->normalizeSettings('platform', $record->settings),
        ]);
    }

    public function tenant(Request $request)
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string', 'exists:tenants,key'],
        ]);

        $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();

        return response()->json([
            'ok' => true,
            'data' => $this->normalizeSettings('tenant', $this->loadTenantSettings($tenant->id), $tenant),
        ]);
    }

    public function updateTenant(Request $request)
    {
        $validated = $this->validateSettings($request, 'tenant');
        $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();

        $record = TenantSetting::firstOrNew(['tenant_id' => $tenant->id]);
        $record->settings = $this->extractSettings($validated);
        $record->save();

        return response()->json([
            'ok' => true,
            'data' => $this->normalizeSettings('tenant', $record->settings, $tenant),
        ]);
    }

    private function validateSettings(Request $request, string $scope): array
    {
        return $request->validate([
            'scope' => ['required', 'in:platform,tenant'],
            'tenantKey' => [$scope === 'tenant' ? 'required' : 'nullable', 'string', 'exists:tenants,key'],
            'workspace_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'location' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'max:10'],
            'theme' => ['required', 'string', 'max:100'],
            'notifications' => ['required', 'array'],
            'notifications.refund_status_updates' => ['required', 'boolean'],
            'notifications.reschedule_approvals' => ['required', 'boolean'],
            'notifications.daily_booking_digest' => ['required', 'boolean'],
            'notifications.agency_onboarding_alerts' => ['required', 'boolean'],
            'notifications.security_alerts' => ['required', 'boolean'],
            'notifications.operational_alerts' => ['required', 'boolean'],
        ]);
    }

    private function extractSettings(array $validated): array
    {
        return [
            'workspace_name' => $validated['workspace_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'location' => $validated['location'],
            'timezone' => $validated['timezone'],
            'currency' => $validated['currency'],
            'theme' => $validated['theme'],
            'notifications' => $validated['notifications'],
        ];
    }

    private function normalizeSettings(string $scope, array|null $settings, ?Tenant $tenant = null): array
    {
        $defaults = $this->defaultSettings($scope, $tenant);
        $data = is_array($settings) ? array_replace_recursive($defaults, $settings) : $defaults;

        if (! isset($data['notifications']) || ! is_array($data['notifications'])) {
            $data['notifications'] = $defaults['notifications'];
        }

        return [
            'scope' => $scope,
            'workspace_name' => $data['workspace_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'location' => $data['location'],
            'timezone' => $data['timezone'],
            'currency' => $data['currency'],
            'theme' => $data['theme'],
            'notifications' => [
                'refund_status_updates' => (bool) ($data['notifications']['refund_status_updates'] ?? false),
                'reschedule_approvals' => (bool) ($data['notifications']['reschedule_approvals'] ?? false),
                'daily_booking_digest' => (bool) ($data['notifications']['daily_booking_digest'] ?? false),
                'agency_onboarding_alerts' => (bool) ($data['notifications']['agency_onboarding_alerts'] ?? false),
                'security_alerts' => (bool) ($data['notifications']['security_alerts'] ?? false),
                'operational_alerts' => (bool) ($data['notifications']['operational_alerts'] ?? false),
            ],
        ];
    }

    private function defaultSettings(string $scope, ?Tenant $tenant = null): array
    {
        $notifications = [
            'refund_status_updates' => true,
            'reschedule_approvals' => true,
            'daily_booking_digest' => true,
            'agency_onboarding_alerts' => true,
            'security_alerts' => true,
            'operational_alerts' => true,
        ];

        if ($scope === 'platform') {
            return [
                'workspace_name' => config('app.name', 'Flight Portal'),
                'email' => 'support@flightportal.com',
                'phone' => '+94 11 200 0000',
                'location' => 'Platform operations center',
                'timezone' => 'Asia/Colombo',
                'currency' => 'USD',
                'theme' => 'Portal Blue',
                'notifications' => $notifications,
            ];
        }

        return [
            'workspace_name' => $tenant?->name ?? 'Tenant Workspace',
            'email' => 'ops@example.com',
            'phone' => '+94 11 200 0000',
            'location' => 'Workspace address',
            'timezone' => 'Asia/Colombo',
            'currency' => 'USD',
            'theme' => 'Portal Blue',
            'notifications' => $notifications,
        ];
    }

    private function loadPlatformSettings(): array
    {
        $record = PlatformSetting::where('key', 'default')->first();
        return $record?->settings ?? [];
    }

    private function loadTenantSettings(int $tenantId): array
    {
        $record = TenantSetting::where('tenant_id', $tenantId)->first();
        return $record?->settings ?? [];
    }
}
