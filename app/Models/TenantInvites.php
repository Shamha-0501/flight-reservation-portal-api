<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantInvites extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_invites';
    protected $fillable = [
        'tenant_id',
        'email',
        'role',
        'token',
        'expires_at',
        'accepted_at',
        'invited_by_user_id'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
