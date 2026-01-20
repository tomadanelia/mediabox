<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
class SubscriptionPlan extends Model
{
    use hasUuid;
    protected $fillable=[
        'id',
        'name_ka',
        'name_en',
        'description_ka',
        'description_en',
        'price',
        'duration_days',
        'is_active',
    ];
    protected $casts=[
        'price'=>'decimal:2',
        'id'=>'string',
        'duration_days'=>'integer',
        'is_active'=>'boolean',
    ];
}
