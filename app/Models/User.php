<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; 
use App\Traits\HasUuid;

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
}