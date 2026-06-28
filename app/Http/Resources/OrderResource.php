<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'tenant_id' => $this->tenant_id,

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'email' => $this->user?->email,
                    'account_state' => $this->user?->account_state,
                    'email_verified_at' => $this->user?->email_verified_at,
                ];
            }),

            'duffel_order_id' => $this->duffel_order_id,
            'booking_reference' => $this->booking_reference,

            'type' => $this->type,
            'status' => $this->status,
            'cancellation_status' => $this->cancellation_status,
            'refund_status' => $this->refund_status,

            'amounts' => [
                'base' => [
                    'amount' => $this->base_amount,
                    'currency' => $this->base_currency,
                ],
                'tax' => [
                    'amount' => $this->tax_amount,
                    'currency' => $this->tax_currency,
                ],
                'total' => [
                    'amount' => $this->total_amount,
                    'currency' => $this->total_currency,
                ],
            ],

            'void_window_ends_at' => $this->void_window_ends_at,
            'synced_at' => $this->synced_at,

            'passengers' => $this->whenLoaded('passengers', function () {
                return $this->passengers->map(fn ($passenger) => [
                    'id' => $passenger->id,
                    'duffel_passenger_id' => $passenger->duffel_passenger_id,
                    'type' => $passenger->type,
                    'title' => $passenger->title,
                    'given_name' => $passenger->given_name,
                    'family_name' => $passenger->family_name,
                    'dob' => $passenger->dob,
                    'gender' => $passenger->gender,
                    'email' => $passenger->email,
                    'phone_number' => $passenger->phone_number,
                    'infant_passenger_id' => $passenger->infant_passenger_id,
                ]);
            }),

            'addons' => $this->whenLoaded('addons', function () {
                return $this->addons;
            }),

            'meta' => [
                'offer' => $this->meta['offer'] ?? null,
                'duffel_order' => $this->meta['duffel_order'] ?? null,
                'cancellation' => $this->meta['cancellation'] ?? null,
            ],

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
