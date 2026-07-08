<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'action' => $this->action,
            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'properties' => $this->properties ?? [],
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'actor' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'email' => $this->user?->email,
                ];
            }),
            'tenant' => $this->whenLoaded('tenant', function () {
                return [
                    'id' => $this->tenant?->id,
                    'key' => $this->tenant?->key,
                    'name' => $this->tenant?->name,
                    'status' => $this->tenant?->status,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
