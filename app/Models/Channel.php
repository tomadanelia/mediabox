<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use App\Models\ChannelCategory;
use Illuminate\Support\Facades\Cache;
use App\Models\SubscriptionPlan;
use App\Models\UserWatchHistory;
class Channel extends Model
{
    use HasUuid;

     protected $fillable = [
        'external_id',  
        'number',       
        'epg_id',       
        'name',
        'icon_url',
        'category_id',
        'is_active',
        'is_free',
        'view_count',
        'is_public'
    ];


    protected $casts = [
        'is_active' => 'boolean',
        'is_free' => 'boolean',
        'view_count' => 'integer',
        'is_public' => 'boolean',
    ];
    public function category()
    {
        return $this->belongsTo(ChannelCategory::class, 'category_id');
    }

public function plans()
{
    return $this->belongsToMany(
        SubscriptionPlan::class,
        'bundle_items',    
        'item_id',         
        'bundle_id'        
    )
    ->wherePivot('item_type', 1)
    ->join('plan_services', 'plan_services.bundle_id', '=', 'bundle_items.bundle_id')
    ->join('subscription_plans', 'subscription_plans.id', '=', 'plan_services.plan_id')
    ->select('subscription_plans.*');
}

public function getRequiredPlanIds(): array
{
    return Cache::remember("channel_plans_{$this->id}", 3600, function () {
        return DB::table('bundle_items')
            ->where('bundle_items.item_type', 1)
            ->where('bundle_items.item_id', $this->id)
            ->join('plan_services', 'plan_services.bundle_id', '=', 'bundle_items.bundle_id')
            ->pluck('plan_services.plan_id')
            ->toArray();
    });
}
    public function viewers()
{
    return $this->hasMany(UserWatchHistory::class);
}
 
    public function streamUrls()
{
    return $this->hasMany(ChannelUrl::class, 'channel_id', 'external_id');
}

    public function archiveUrls()
{
    return $this->hasMany(ChannelArchiveUrl::class, 'channel_id', 'external_id');
}
}