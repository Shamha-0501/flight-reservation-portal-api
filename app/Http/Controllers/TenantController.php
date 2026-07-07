<?php

namespace App\Http\Controllers;

use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function getActiveTenants(Request $request)
    {
        $tenants = Tenant::where('status', 'active')
            ->withCount('users')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Active tenants fetched successfully.',
            'data' => TenantResource::collection($tenants),
        ]);
    }
}
