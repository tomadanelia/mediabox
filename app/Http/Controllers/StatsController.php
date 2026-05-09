<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Channel;

class StatsController extends Controller
{
    private const TTL = 120; 
    public function getMetrics(Request $request)
    {
        $token = $request->header('X-Stats-Token') ?? $request->query('token');
        if ($token !== config('services.flussonic.stats_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $now = time();
        $cutoff = $now - self::TTL;

        $requestedIds = $request->input('channel_ids');
        
        $query = Channel::select('external_id', 'name_en');
        if ($requestedIds) {
            $query->whereIn('external_id', $requestedIds);
        }
        $channels = $query->get();
        Redis::zremrangebyscore('active_viewers:global', 0, $cutoff);
        $globalMembers = Redis::zrange('active_viewers:global', 0, -1);

        $stats = [
            'timestamp' => $now,
            'global' => $this->parseMembers($globalMembers),
            'channels' => []
        ];

        foreach ($channels as $channel) {
            $key = "active_viewers:{$channel->external_id}";
            Redis::zremrangebyscore($key, 0, $cutoff);
            $members = Redis::zrange($key, 0, -1);
            
            $stats['channels'][$channel->external_id] = array_merge(
                ['name' => $channel->name_en],
                $this->parseMembers($members)
            );
        }

        return response()->json($stats);
    }

    /**
     * Helper to parse "userId:platform:socketId" strings
     */
    private function parseMembers(array $members): array
    {
        $spaCount = 0;
        $tvCount = 0;
        $userIds = [];

        foreach ($members as $member) {
            $parts = explode(':', $member);
            if (count($parts) < 3) continue;

            $userId   = $parts[0];
            $platform = $parts[1]; 

            if ($platform === 'spa') $spaCount++;
            elseif ($platform === 'tvapk') $tvCount++;

            $userIds[] = $userId;
        }

        return [
            'total' => count($members),
            'by_platform' => [
                'spa' => $spaCount,
                'tvapk' => $tvCount,
            ],
            'user_ids' => array_values(array_unique($userIds))
        ];
    }
}
