<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAuthToken extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'token',
        'used',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'used' => 'boolean',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    public function isValid(): bool
    {
        return ! $this->used && ! $this->isExpired();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
