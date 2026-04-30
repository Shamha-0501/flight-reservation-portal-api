<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Passenger extends Model
{
    use SoftDeletes;

    protected $table = 'passengers';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'duffel_passenger_id',
        'type',
        'title',
        'given_name',
        'family_name',
        'dob',
        'gender',
        'email',
        'phone_number',
        'infant_passenger_id',
        'meta',
    ];

    protected $casts = [
        'born_on' => 'date',
        'meta' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Infant linked to an adult
    public function infant(): BelongsTo
    {
        return $this->belongsTo(Passenger::class, 'infant_passenger_id');
    }

    // Adult having infants
    public function infants(): HasMany
    {
        return $this->hasMany(Passenger::class, 'infant_passenger_id');
    }
}