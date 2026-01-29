<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use App\Models\ChannelCategory;
use Illuminate\Support\Facades\Cache;
use App\Models\SubscriptionPlan;
class Channel extends Model
{
    use HasUuid;

     protected $fillable = [
        'external_id',  
        'number',       
        'epg_id',       
        'name_ka',
        'name_en',
        'description_ka',
        'description_en',
        'icon_url',
        'category_id',
        'is_active',
        'is_free',
        'view_count'
    ];


    protected $casts = [
        'is_active' => 'boolean',
        'is_free' => 'boolean',
        'view_count' => 'integer',
    ];
    public function getRequiredPlanIds(): array
    {
         return Cache::remember("channel_plans_{$this->id}", 3600, function() {
            return $this->plans()->pluck('subscription_plans.id')->toArray();
        });
    }
    public function category()
    {
        return $this->belongsTo(ChannelCategory::class, 'category_id');
    }
    public function plans()
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'channel_subscription_plan', 'channel_id', 'plan_id');
    }
}