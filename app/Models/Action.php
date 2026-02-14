<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Action extends Model
{
    use SoftDeletes;

    protected $table = 'pbac_actions';
    protected $fillable = [
        'action',
        'label'
    ];
}
