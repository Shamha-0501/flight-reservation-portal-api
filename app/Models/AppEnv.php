<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppEnv extends Model
{
    protected $table = 'app_env';

    protected $fillable = [
        'key',
        'value',
        'is_secret',
    ];
}