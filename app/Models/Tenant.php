<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user', 'tenant_id', 'user_id')
            ->using(TenantUser::class)
            ->withPivot([
                'role_id',
                'invited_by_user_id',
                'status',
                'deleted_at',
                'created_at',
                'updated_at',
            ])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function markupSetting(): HasOne
    {
        return $this->hasOne(AgencyMarkupSetting::class)
            ->latestOfMany();
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->key)) {
                $tenant->key = (string) Str::uuid();
            }
        });
    }
}
