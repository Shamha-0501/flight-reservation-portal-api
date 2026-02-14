<?php

namespace App\Services\Pbac;

use App\Models\Policy;
use Illuminate\Support\Collection;

class PbacPolicyLoader
{
    /**
     * Load all PBAC policies applicable to a user for a permission
     * 
     * @param ?int  $tenantId
     * @param int   $userId
     * @param array $roleIds
     * @param int   $permissionId
     * 
     * @return array
     */
    public function load(
        ?int $tenantId,
        int $userId,
        array $roleIds,
        int $permissionId
    ): array {
        $policies = Policy::query()
            ->where('is_enabled', true)
            ->where('permission_id', $permissionId)

            // Tenant / Global
            ->where(function($q) use ($tenantId){
                if ($tenantId !== null) {
                    $q->where('tenant_id', $tenantId)
                      ->orWhereNull('tenant_id');
                } else {
                    $q->whereNull('tenant_id');
                }
            })

            ->whereHas('subjects', function($q) use ($userId, $roleIds) {
                $q->where('subject_type', 'tenant')
                  ->orWhere(function($q) use ($userId) {
                    $q->where('subject_type', 'user')
                      ->where('subject_id', $userId);
                  })
                  ->orWhere(function($q) use ($roleIds) {
                    if (!empty($roleIds)) {
                        $q->where('subject_type', 'role')
                          ->whereIn('subject_id', $roleIds);
                    }
                  });
            })

            ->with('conditions')

            // tenant-specific wins over global
            ->orderByRaw('tenant_id IS NULL')
            ->orderByDesc('priority')

            ->get();
        
        return $this->normalize($policies);
    }

    protected function normalize(Collection $policies): array
    {
        return $policies->map(function($policy) {
            return [
                'id' => $policy->id,
                'effect' => $policy->effect,
                'priority' => $policy->priority,
                'tenant_id' => $policy->tenant_id,
                'conditions' => $policy->conditions->map(function($c) {
                    return [
                        'left' => $c->left_operand,
                        'operator' => $c->operator,
                        'right_type' => $c->right_type,
                        'right' => $c->right_value,
                        'right_ref' => $c->right_ref,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }
}
