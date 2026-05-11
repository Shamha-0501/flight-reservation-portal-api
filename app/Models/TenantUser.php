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
    ];

    protected $dates = [
        'deleted_at',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(UserRole::class, 'role_id');
    }
}
