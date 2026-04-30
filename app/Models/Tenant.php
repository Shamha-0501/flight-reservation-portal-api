<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use SoftDeletes;

    protected $table = 'tenants';

    protected $fillable = [
        'key',
        'name',
        'status',
        'timezone',
        'locale',
        'created_by_user_id',
        'trial_ends_at',
        'suspended_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            logger()->info('creating fired', $tenant->toArray());

            if (empty($tenant->key)) {
                $tenant->key = (string) Str::uuid();
            }
        });
    }
}
