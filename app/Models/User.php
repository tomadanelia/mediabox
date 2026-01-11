<?php

namespace App\Models;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; 
use App\Traits\HasUuid;

class User extends Authenticatable implements FilamentUser, HasName
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
        'subscription_status'
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
            'subscription_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function account()
{
    return $this->hasOne(Account::class);
}
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'admin';
    }

    public function getFilamentName(): string
    {
        return $this->full_name ?? $this->username ?? $this->email ?? 'User';
    }
}