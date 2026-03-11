<?php
namespace App\Services;

use Firebase\JWT\JWT;

class SocketTokenService
{
    public function generateToken(string $userId, string $deviceId): string
    {
        $payload = [
            'iss' => config('app.url'),
            'iat' => time(),
            'exp' => time() + 3600,
            'sub' => $userId,
            'device_id' => $deviceId,
        ];

        return JWT::encode($payload, config('app.jwt_socket_secret'), config('app.jwt_algo'));
    }
}
