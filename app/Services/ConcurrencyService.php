<?php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class ConcurrencyService
{
    private const MAX_DEVICES = 2;
    private const SESSION_TTL = 300; 

   public function heartbeat(string $userId, string $deviceId, string $ip): bool
{
    $key = "user_stream_sessions:{$userId}";
    $ipKey = "session_ip:{$userId}:{$deviceId}";
    $now = time();

    Redis::zremrangebyscore($key, 0, $now - self::SESSION_TTL);

    $storedIp = Redis::get($ipKey);

    if (Redis::zscore($key, $deviceId) !== false) {

        if ($storedIp && $storedIp !== $ip) {
            return false; 
        }

        Redis::zadd($key, $now, $deviceId);
        Redis::setex($ipKey, self::SESSION_TTL, $ip);
        Redis::expire($key, self::SESSION_TTL + 60);

        return true;
    }

    $count = Redis::zcard($key);

    if ($count >= self::MAX_DEVICES) {
        return false;
    }

    Redis::zadd($key, $now, $deviceId);
    Redis::setex($ipKey, self::SESSION_TTL, $ip);
    Redis::expire($key, self::SESSION_TTL + 60);

    return true;
}
  public function isSessionAlive(string $userId, string $deviceId, string $ip): bool
{
    $key = "user_stream_sessions:{$userId}";
    $ipKey = "session_ip:{$userId}:{$deviceId}";
    $now = time();

    Redis::zremrangebyscore($key, 0, $now - self::SESSION_TTL);

    if (Redis::zscore($key, $deviceId) === false) {
        return false;
    }

    $storedIp = Redis::get($ipKey);
    return $storedIp === $ip;
}
}