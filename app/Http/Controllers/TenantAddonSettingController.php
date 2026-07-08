<?php

namespace App\Http\Controllers;

use App\Http\Resources\TenantAddonSettingResource;
use App\Models\Tenant;
use App\Models\TenantAddonSetting;
use Illuminate\Http\Request;

class TenantAddonSettingController extends Controller
{
    public function getTenantAddonSettings(Request $request)
    {
        $validated = $request->validate([
            'tenantKey' => 'required|string'
        ]);

        $tenantKey = $validated['tenantKey'];
        $tenant = Tenant::where('key', $tenantKey)->first();
        abort_if(! $tenant, 404, 'Tenant not found.');
        $settings = TenantAddonSetting::where('tenant_id', $tenant->id)->first();

        if (!$settings) {
            return response()->json([
                'message' => 'Addon settings not found for this tenant.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Tenant addon settings fetched successfully.',
            'data' => new TenantAddonSettingResource($settings),
        ]);
    }
}
