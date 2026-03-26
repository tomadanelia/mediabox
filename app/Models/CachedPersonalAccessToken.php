<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

class CachedPersonalAccessToken extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    public static function findToken($token)
    {
        $actualToken = str_contains($token, '|') 
            ? explode('|', $token, 2)[1] 
            : $token;
            
        $hashed = hash('sha256', $actualToken);

        return Cache::remember("sanctum:{$hashed}", 900, function () use ($token) {
            return parent::findToken($token);
        });
    }

    public function delete()
    {
        Cache::forget("sanctum:{$this->token}");
        return parent::delete();
    }
}