<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class ServiceBundle extends Model {
    use HasUuid;
    protected $fillable = ['slug', 'name', 'type', 'is_active'];
    public function items() { return $this->hasMany(BundleItem::class, 'bundle_id'); }
    public function plans() { return $this->belongsToMany(SubscriptionPlan::class, 'plan_services', 'bundle_id', 'plan_id'); }
}
