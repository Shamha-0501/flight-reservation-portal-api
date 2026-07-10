<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAddon extends Model
{
    protected $table = 'booking_addons';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'addon_id',
        'addon_code',
        'addon_name',
        'price',
        'currency',
        'meta',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(PlatformAddon::class, 'addon_id');
    }
}
