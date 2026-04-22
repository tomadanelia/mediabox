<?php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class BroadcastService
{
    /**
     * Send a message to EVERYONE connected to the platform
     */
    public function sendGlobalAnnouncement(string $id, string $title, string $message)
{
    Redis::publish('broadcast_notifications', json_encode([
        'type' => 'global',
        'event' => 'admin_announcement',
        'data' => [
            'id'        => $id, 
            'title'     => $title,
            'message'   => $message,
            'timestamp' => now()->toIso8601String()
        ]
    ]));
}

    /**
     * Send a message to a SPECIFIC user (all their devices)
     */
    public function sendUserNotify(string $userId, string $event, array $data)
    {
        Redis::publish('broadcast_notifications', json_encode([
            'type' => 'user',
            'target' => $userId,
            'event' => $event,
            'data' => $data
        ]));
    }
}