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
use App\Models\UserSubscription;
use App\Models\Channel;
use App\Models\UserDevice;


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
        'numeric_id'
        
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
    public function getActivePlanIds(): ?array
{
    return Cache::remember("user_plan_ids_{$this->id}", 300, function(){
    return $this->subscriptionPlans()
            ->wherePivot('is_active', true)
            ->wherePivot('expires_at', '>', now())
            ->pluck('subscription_plans.id')
            ->toArray();
  });
}
    public function isAdmin(): bool
{
    return $this->role === 'admin';
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
      public function favouriteChannels(){
    return $this->belongsToMany(
        Channel::class,
        'user_favourites',
        'user_id',
        'channel_id',
    )
    ->withTimestamps();
    }
    public function watchHistories()
{
    return $this->hasMany(UserWatchHistory::class);
}
    public function devices()
{
    return $this->hasMany(UserDevice::class);
}
   protected static function booted()
{
    static::creating(function ($user) {
        $user->numeric_id = (User::max('numeric_id') ?? 99999) + 1;
    });
}
// Migration: 2024_xx_xx_make_numeric_id_autoincrement.php

//public function up(): void
//{
    // TODO: Uncomment when deploying to MySQL
    // This makes numeric_id truly auto-increment at DB level
    // Run AFTER switching from SQLite to MySQL
    
    // Schema::table('users', function (Blueprint $table) {
    //     $table->unsignedBigInteger('numeric_id')
    //           ->nullable(false)
    //           ->change();
    // });
    
    // DB::statement('ALTER TABLE users MODIFY numeric_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE;');
    // DB::statement('ALTER TABLE users AUTO_INCREMENT = 100000;');
//}
}