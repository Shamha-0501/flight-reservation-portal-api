<?php

namespace App\Http\Resources;

use App\Support\RoleCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->role ?? null;
        $roleKey = $role?->key ?? null;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'role' => $role?->name ?? RoleCatalog::label($roleKey),
            'role_key' => $roleKey,
            'status' => $this->status,
            'invited_by_user_id' => $this->invited_by_user_id,
            'invited_by' => $this->inviter?->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
