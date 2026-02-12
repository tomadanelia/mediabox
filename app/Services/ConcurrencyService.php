<?php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class ConcurrencyService
{
    private const MAX_DEVICES = 2;
    private const SESSION_TTL = 300; 

    public function heartbeat(string $userId, string $deviceId): bool
    {
        $key = "user_stream_sessions:{$userId}";
        $now = time();

       
        Redis::zremrangebyscore($key, 0, $now - self::SESSION_TTL);

        if (Redis::zscore($key, $deviceId)) {
            Redis::zadd($key, $now, $deviceId);
            Redis::expire($key, self::SESSION_TTL + 60); 
            return true;
        }

        $count = Redis::zcard($key);

        if ($count < self::MAX_DEVICES) {
            Redis::zadd($key, $now, $deviceId);
            Redis::expire($key, self::SESSION_TTL + 60);
            return true;
        }

        return false;
    }
}