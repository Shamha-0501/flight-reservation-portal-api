<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name'   => $this->name,
            'email'  => $this->email,
            'tenants' => $this->whenLoaded('tenants', function () {
                return $this->tenants->map(fn ($t) => [
                    'id'   => $t->id,
                    'key'  => $t->key,
                    'name' => $t->name,
                    // pivot fields if you want them:
                    'role' => $t->pivot?->role,
                    'status' => $t->pivot?->status,
                    'joined_at' => $t->pivot?->joined_at,
                ]);
            }),
        ];
    }
}
