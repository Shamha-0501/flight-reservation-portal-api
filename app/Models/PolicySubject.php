<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PolicySubject extends Model
{
    use SoftDeletes;

    protected $table = 'pbac_policy_subject';
    protected $fillable = [
        'tenant_id',
        'policy_id',
        'subject_type',
        'subject_id',
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
