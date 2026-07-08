<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogger
{
    public function log(
        string $action,
        ?Request $request = null,
        ?Tenant $tenant = null,
        ?User $actor = null,
        ?Model $subject = null,
        ?string $title = null,
        ?string $description = null,
        ?string $category = null,
        array $properties = [],
    ): ActivityLog {
        return ActivityLog::create([
            'tenant_id' => $tenant?->id ?? $this->resolveTenantIdFromSubject($subject),
            'user_id' => $actor?->id,
            'action' => $action,
            'category' => $category,
            'title' => $title ?? $action,
            'description' => $description,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private function resolveTenantIdFromSubject(?Model $subject): ?int
    {
        if (! $subject) {
            return null;
        }

        $tenantId = $subject->getAttribute('tenant_id');

        return is_numeric($tenantId) ? (int) $tenantId : null;
    }
}
