<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'status' => $this->status,
            'is_active' => true,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'trial_ends_at' => $this->trial_ends_at,
            'suspended_at' => $this->suspended_at,
            'created_by_user_id' => $this->created_by_user_id,
            'meta' => $this->meta,
            'member_count' => $this->users_count,

            'markup' => [
                'mode' => $this->markup_mode,
                'value' => $this->markup_value,
                'currency' => $this->markup_currency,
            ],

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}