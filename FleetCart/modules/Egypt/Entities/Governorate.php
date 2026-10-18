<?php

namespace Modules\Egypt\Entities;

use Illuminate\Database\Eloquent\Model;

class Governorate extends Model
{
    protected $table = 'egypt_governorates';

    protected $fillable = [
        'code', 'name_en', 'name_ar', 'shipping_cost', 'free_shipping_over',
        'eta_days_min', 'eta_days_max', 'is_active', 'position',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'shipping_cost' => 'float',
        'free_shipping_over' => 'float',
    ];


    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
