<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PolicyContext extends Model
{
    use SoftDeletes;

    protected $table = 'pbac_policy_context';
    protected $fillable = [
        'tenant_id',
        'policy_id',
        'scope',
        'left_operant',
        'operator',
        'right_type',
        'right_ref',
        'right_value_string',
        'right_value_int',
        'right_value_bool',
        'right_value_decimal'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function policy()
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }
}
