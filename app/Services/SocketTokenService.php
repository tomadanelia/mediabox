<?php
namespace App\Services;

use Firebase\JWT\JWT;

class SocketTokenService
{
    public function generateToken(string $userId, string $deviceId, string $platform, string $ip, string $os, string $version): string
    {
        $payload = [
            'sub'         => $userId,
            'device_id'   => $deviceId,
            'platform'    => $platform,
            'ip'          => $ip,
            'os'          => $os,
            'apk_version' => $version,
            'iat'         => time(),
            'exp'         => time() + (3600 * 12),
        ];

        return JWT::encode($payload, config('app.jwt_socket_secret'), config('app.jwt_algo'));
    }
}
