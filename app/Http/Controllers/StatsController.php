<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Models\Channel;

class StatsController extends Controller
{
    private const TTL = 120;
    private const CHANNEL_CACHE_TTL = 300; // cache channel list 5 min

    public function getMetrics(Request $request)
    {
        $token = $request->header('X-Stats-Token') ?? $request->query('token');
        if ($token !== config('services.flussonic.stats_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $now    = time();
        $cutoff = $now - self::TTL;

        $requestedIds = $request->input('channel_ids');

        $cacheKey   = 'stats_channel_map' . ($requestedIds ? md5(implode(',', $requestedIds)) : '_all');
        $channelMap = Cache::remember($cacheKey, self::CHANNEL_CACHE_TTL, function () use ($requestedIds) {
            $query = Channel::select('external_id', 'name_en');
            if ($requestedIds) {
                $query->whereIn('external_id', $requestedIds);
            }
            return $query->pluck('name_en', 'external_id')->all();
        });

        $pipe = Redis::pipeline();
        $pipe->zrangebyscore('active_viewers:global', $cutoff, '+inf');
        foreach (array_keys($channelMap) as $externalId) {
            $pipe->zrangebyscore("active_viewers:{$externalId}", $cutoff, '+inf');
        }
        $results = $pipe->execute();

        $globalMembers = array_shift($results);

        $stats = [
            'timestamp' => $now,
            'global'    => $this->parseMembers($globalMembers),
            'channels'  => [],
        ];

        $externalIds = array_keys($channelMap);
        foreach ($results as $i => $members) {
            $externalId = $externalIds[$i];
            $stats['channels'][$externalId] = array_merge(
                ['name' => $channelMap[$externalId]],
                $this->parseMembers($members)
            );
        }

        return response()->json($stats);
    }

    private function parseMembers(array $members): array
    {
        $spaCount = 0;
        $tvCount  = 0;
        $userIds  = [];

        foreach ($members as $member) {
            $parts = explode(':', $member, 3); 
            if (count($parts) < 3) continue;

            [$userId, $platform] = $parts;

            if ($platform === 'spa')    $spaCount++;
            elseif ($platform === 'tvapk') $tvCount++;

            $userIds[$userId] = true; 
        }

        return [
            'total'       => count($members),
            'by_platform' => ['spa' => $spaCount, 'tvapk' => $tvCount],
            'user_ids'    => array_keys($userIds),
        ];
    }
}