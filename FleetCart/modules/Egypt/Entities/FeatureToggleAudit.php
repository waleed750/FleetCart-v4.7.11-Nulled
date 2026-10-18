<?php

namespace Modules\Egypt\Entities;

use Illuminate\Database\Eloquent\Model;

class FeatureToggleAudit extends Model
{
    protected $table = 'egypt_feature_toggle_audits';

    public $timestamps = false;

    protected $fillable = ['feature_key', 'old_value', 'new_value', 'user_id', 'user_email', 'ip'];

    protected $casts = [
        'old_value' => 'boolean',
        'new_value' => 'boolean',
        'created_at' => 'datetime',
    ];
}
