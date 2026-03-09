<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Illuminate\Support\Facades\Cache;

class RadioChannel extends Model
{
    use HasUuid;

    protected $fillable = ['external_id', 'name', 'icon_url', 'is_active', 'is_free'];

    public function plans()
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'radio_subscription_plan', 'radio_id', 'plan_id');
    }

    public function getRequiredPlanIds(): array
    {
        return Cache::remember("radio_required_plans_{$this->id}", 3600, function() {
            return $this->plans()->pluck('subscription_plans.id')->toArray();
        });
    }
}