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
            ->where('status', 'active')
            ->with([
                'markupSetting' => function ($query) {
                    $query->select([
                        'agency_markup_settings.id',
                        'agency_markup_settings.tenant_id',
                        'agency_markup_settings.markup_mode',
                        'agency_markup_settings.markup_value',
                        'agency_markup_settings.currency',
                    ]);
                },
            ])
            ->withCount('users')
            ->latest('created_at')
            ->get();

        return response()->json([
            'message' => 'Active tenants fetched successfully.',
            'data' => TenantResource::collection($tenants),
        ]);
    }
}
