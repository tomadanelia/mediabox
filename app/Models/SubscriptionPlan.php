<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'is_public',
    ];
    protected $casts=[
        'price'=>'decimal:2',
        'id'=>'string',
        'duration_days'=>'integer',
        'is_active'=>'boolean',
        'is_public'=>'boolean',
    ];
    public function bundles(): BelongsToMany
{
    return $this->belongsToMany(
        ServiceBundle::class,
        'plan_services',
        'plan_id',
        'bundle_id'
    );
}

    public function users()
{
    return $this->belongsToMany(
        User::class, 
        'user_subscriptions', 
        'plan_id', 
        'user_id'
    )->withPivot('expires_at', 'is_active');
}
}
