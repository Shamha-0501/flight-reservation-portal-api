<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'orders';

    public const STATUS_BOOKED = 'Booked';
    public const STATUS_CANCELLATION_REQUESTED = 'Cancellation Requested';
    public const STATUS_CANCELLED = 'Cancelled';
    public const STATUS_REFUNDED = 'Refunded';

    public const CANCELLATION_STATUS_NONE = 'Not Cancelled';
    public const CANCELLATION_STATUS_REQUESTED = 'Cancellation Requested';
    public const CANCELLATION_STATUS_CANCELLED = 'Cancelled';

    public const REFUND_STATUS_NONE = 'No Refund';
    public const REFUND_STATUS_PENDING = 'Refund Pending';
    public const REFUND_STATUS_UNKNOWN = 'Refund Unknown';
    public const REFUND_STATUS_REFUNDED = 'Refunded';

    public const CANCELLATION_FINAL_STATUSES = [
        self::CANCELLATION_STATUS_CANCELLED,
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'duffel_order_id',
        'booking_reference',
        'type',
        'status',
        'cancellation_status',
        'refund_status',
        'base_amount',
        'base_currency',
        'tax_amount',
        'tax_currency',
        'total_amount',
        'total_currency',
        'synced_at',
        'void_window_ends_at',
        'meta',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'synced_at' => 'datetime',
        'void_window_ends_at' => 'datetime',
        'meta' => 'array',
    ];

    public function getBaseCurrencyAttribute($value): string
    {
        return $value ?: config('finance.default_currency', 'LKR');
    }

    public function getTaxCurrencyAttribute($value): string
    {
        return $value ?: config('finance.default_currency', 'LKR');
    }

    public function getTotalCurrencyAttribute($value): string
    {
        return $value ?: config('finance.default_currency', 'LKR');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(Passenger::class);
    }
}
