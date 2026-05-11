<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\UserRole;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name'  => $this->name,
            'email' => $this->email,

            'tenants' => $this->whenLoaded('tenants', function () {
                return $this->tenants->map(function ($tenant) {
                    $role = UserRole::find($tenant->pivot?->role_id);

                    return [
                        'id'   => $tenant->id,
                        'key'  => $tenant->key,
                        'name' => $tenant->name,
                        'role' => $role->name,
                    ];
                });
            }),
        ];
    }
}