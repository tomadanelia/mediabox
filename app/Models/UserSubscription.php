<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Traits\HasUuid;
use App\Models\SubscriptionPlan;
class UserSubscription extends Pivot
{
    use HasUuid;
    protected $table = 'user_subscriptions';

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'auto_renew' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
