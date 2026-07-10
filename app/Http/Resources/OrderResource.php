<?php

namespace App\Http\Resources;

use App\Services\CurrencyConverter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'tenant_id' => $this->tenant_id,
            'tenant_key' => $this->whenLoaded('tenant', function () {
                return $this->tenant?->key;
            }),
            'tenant_name' => $this->whenLoaded('tenant', function () {
                return $this->tenant?->name;
            }),
            'tenant' => $this->whenLoaded('tenant', function () {
                return [
                    'id' => $this->tenant?->id,
                    'key' => $this->tenant?->key,
                    'name' => $this->tenant?->name,
                ];
            }),

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
                    'amount' => $this->moneyAmount('base_amount', 'base_currency'),
                    'currency' => $this->currencyCode('base_currency'),
                ],
                'tax' => [
                    'amount' => $this->moneyAmount('tax_amount', 'tax_currency'),
                    'currency' => $this->currencyCode('tax_currency'),
                ],
                'total' => [
                    'amount' => $this->moneyAmount('total_amount', 'total_currency'),
                    'currency' => $this->currencyCode('total_currency'),
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
                return $this->addons->map(function ($addon) {
                    return app(CurrencyConverter::class)->convertPayload($addon->getAttributes());
                });
            }),

            'meta' => [
                'offer' => app(CurrencyConverter::class)->convertPayload($this->meta['offer'] ?? null),
                'duffel_order' => app(CurrencyConverter::class)->convertPayload($this->meta['duffel_order'] ?? null),
                'cancellation' => app(CurrencyConverter::class)->convertPayload($this->meta['cancellation'] ?? null),
                'change' => app(CurrencyConverter::class)->convertPayload($this->meta['change'] ?? null),
                'agency_markup' => app(CurrencyConverter::class)->convertPayload($this->meta['agency_markup'] ?? null),
            ],

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function moneyAmount(string $amountKey, string $currencyKey): ?string
    {
        $amount = $this->getRawOriginal($amountKey) ?? $this->{$amountKey};
        $currency = $this->currencyCode($currencyKey);

        return app(CurrencyConverter::class)->convertAmount($amount, $currency);
    }

    private function currencyCode(string $currencyKey): string
    {
        return config('finance.default_currency', 'LKR');
    }
}
