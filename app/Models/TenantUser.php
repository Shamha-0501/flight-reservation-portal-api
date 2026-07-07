<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantUser extends Pivot
{
    use SoftDeletes;

    protected $table = 'tenant_user';

    // Important for pivot models if you want mass assignment / attach data
    protected $fillable = [
        'tenant_id',
        'user_id',
        'role_id',
        'invited_by_user_id',
        'status',
    ];

    protected $dates = [
        'deleted_at',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
