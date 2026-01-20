<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; 
use App\Traits\HasUuid;
use App\Models\Account;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Cache;


class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuid, HasApiTokens; 

    protected $fillable = [
        'username',
        'email',
        'phone', 
        'password',
        'full_name',
        'avatar_url',
        'role',
        
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime', 
            'password' => 'hashed',
        ];
    }
  public function getActivePlanName(): ?string
{
    return Cache::remember("user_plan_{$this->id}", 300, function() {
       return $this->subscriptionPlans()
            ->wherePivot('is_active', true)
            ->wherePivot('expires_at', '>', now())
            ->value('name_en'); 
    });
}
    public function account()
{
    return $this->hasOne(Account::class);
}
    public function subscriptionPlans()
{
    return $this->belongsToMany(
        SubscriptionPlan::class,
        'user_subscriptions',
        'user_id',
        'plan_id'
    )
    ->using(UserSubscription::class)
    ->withPivot([
        'started_at',
        'expires_at',
        'is_active',
        'auto_renew'
    ])
    ->withTimestamps();
}

}