<?php

namespace App\Http\Controllers;

use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function getActiveTenants(Request $request)
    {
        // $tenants = Tenant::where('status', 'active')
        //     ->withCount('users')
        //     ->latest()
        //     ->get();

        $tenants = Tenant::query()
            ->leftJoin('agency_markup_settings as ams', 'tenants.id', '=', 'ams.tenant_id')
            ->where('tenants.status', 'active')
            ->select([
                'tenants.id',
                'tenants.key',
                'tenants.name',
                'tenants.status',
                'tenants.timezone',
                'tenants.locale',
                'tenants.trial_ends_at',
                'tenants.suspended_at',
                'tenants.created_by_user_id',
                'tenants.meta',
                'ams.markup_mode',
                'ams.markup_value',
                'ams.currency'
            ])
            ->withCount('users')
            ->latest('tenants.created_at')
            ->get();

        return response()->json([
            'message' => 'Active tenants fetched successfully.',
            'data' => TenantResource::collection($tenants),
        ]);
    }
}
