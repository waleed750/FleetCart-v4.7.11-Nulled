<?php

namespace Modules\Egypt\Entities;

use Illuminate\Database\Eloquent\Model;

class FeatureToggle extends Model
{
    protected $table = 'egypt_feature_toggles';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'is_enabled', 'updated_by'];

    protected $casts = ['is_enabled' => 'boolean'];
}
