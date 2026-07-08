<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformSetting extends Model
{
    use SoftDeletes;

    protected $table = 'platform_settings';

    protected $fillable = [
        'key',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];
}
