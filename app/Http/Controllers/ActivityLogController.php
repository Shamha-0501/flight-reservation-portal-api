<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Tenant;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function tenantIndex(Request $request)
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string', 'exists:tenants,key'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();
        $perPage = (int) ($validated['per_page'] ?? 20);

        $activities = ActivityLog::query()
            ->where('tenant_id', $tenant->id)
            ->with(['user', 'tenant'])
            ->latest()
            ->paginate($perPage);

        return ActivityLogResource::collection($activities);
    }

    public function adminIndex(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $activities = ActivityLog::query()
            ->with(['user', 'tenant'])
            ->when(
                ! empty($validated['tenant_id']),
                fn ($query) => $query->where('tenant_id', $validated['tenant_id'])
            )
            ->latest()
            ->paginate($perPage);

        return ActivityLogResource::collection($activities);
    }
}
