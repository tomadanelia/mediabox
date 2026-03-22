<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

class CachedPersonalAccessToken extends PersonalAccessToken
{
    //ttl set to 15 minutes
    public static function findToken($token)
    {
        $hashed = hash('sha256', $token);

        return Cache::remember("sanctum:{$hashed}", 900, fn() =>
            parent::findToken($token)
        );
    }
}