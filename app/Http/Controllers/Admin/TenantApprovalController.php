<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class TenantApprovalController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function pending(Request $request)
    {
        $tenants = Tenant::query()
            ->whereIn('status', ['pending', 'suspended', 'rejected'])
            ->withCount('users')
            ->latest()
            ->get();

        return response()->json([
            'ok' => true,
            'data' => TenantResource::collection($tenants),
        ]);
    }

    public function approve(Request $request, Tenant $tenant)
    {
        $tenant->forceFill([
            'status' => 'active',
            'suspended_at' => null,
        ])->save();

        $this->activityLogger->log(
            action: 'tenant.approved',
            request: $request,
            tenant: $tenant,
            actor: $request->user(),
            subject: $tenant,
            title: 'Tenant approved',
            description: "Tenant {$tenant->name} was approved for workspace access.",
            category: 'tenant',
            properties: [
                'tenant_key' => $tenant->key,
                'status' => $tenant->status,
            ],
        );

        return response()->json([
            'ok' => true,
            'tenant' => TenantResource::make($tenant->loadCount('users')),
        ]);
    }

    public function reject(Request $request, Tenant $tenant)
    {
        $tenant->forceFill([
            'status' => 'rejected',
            'suspended_at' => null,
        ])->save();

        $this->activityLogger->log(
            action: 'tenant.rejected',
            request: $request,
            tenant: $tenant,
            actor: $request->user(),
            subject: $tenant,
            title: 'Tenant rejected',
            description: "Tenant {$tenant->name} was rejected.",
            category: 'tenant',
            properties: [
                'tenant_key' => $tenant->key,
                'status' => $tenant->status,
            ],
        );

        return response()->json([
            'ok' => true,
            'tenant' => TenantResource::make($tenant->loadCount('users')),
        ]);
    }

    public function suspend(Request $request, Tenant $tenant)
    {
        $tenant->forceFill([
            'status' => 'suspended',
            'suspended_at' => now(),
        ])->save();

        $this->activityLogger->log(
            action: 'tenant.suspended',
            request: $request,
            tenant: $tenant,
            actor: $request->user(),
            subject: $tenant,
            title: 'Tenant suspended',
            description: "Tenant {$tenant->name} was suspended.",
            category: 'tenant',
            properties: [
                'tenant_key' => $tenant->key,
                'status' => $tenant->status,
                'suspended_at' => $tenant->suspended_at?->toISOString(),
            ],
        );

        return response()->json([
            'ok' => true,
            'tenant' => TenantResource::make($tenant->loadCount('users')),
        ]);
    }

    public function reactivate(Request $request, Tenant $tenant)
    {
        $tenant->forceFill([
            'status' => 'active',
            'suspended_at' => null,
        ])->save();

        $this->activityLogger->log(
            action: 'tenant.reactivated',
            request: $request,
            tenant: $tenant,
            actor: $request->user(),
            subject: $tenant,
            title: 'Tenant reactivated',
            description: "Tenant {$tenant->name} was reactivated.",
            category: 'tenant',
            properties: [
                'tenant_key' => $tenant->key,
                'status' => $tenant->status,
            ],
        );

        return response()->json([
            'ok' => true,
            'tenant' => TenantResource::make($tenant->loadCount('users')),
        ]);
    }
}
