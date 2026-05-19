<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Models\Channel;

class StatsController extends Controller
{
    private const TTL = 120;
    private const CHANNEL_CACHE_TTL = 300; 

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
            $query = Channel::select('external_id', 'name');
            if ($requestedIds) {
                $query->whereIn('external_id', $requestedIds);
            }
            return $query->pluck('name', 'external_id')->all();
        });

        $results = Redis::pipeline(function ($pipe) use ($cutoff, $channelMap) {
        $pipe->zrangebyscore('active_viewers:global', $cutoff, '+inf');
        $pipe->zrangebyscore('active_viewers:idle', $cutoff, '+inf');
         foreach (array_keys($channelMap) as $externalId) {
            $pipe->zrangebyscore("active_viewers:{$externalId}", $cutoff, '+inf');
         }
        });
        $globalMembers = array_shift($results);
        $idleMembers = array_shift($results);

        $stats = [
            'timestamp' => $now,
            'global'    => $this->parseMembers($globalMembers),
            'idle'     => $this->parseMembers($idleMembers),
            'summary' => [
                'total_online' => count($globalMembers),
                'watching_tv'  => count($globalMembers) - count($idleMembers),
                'in_menu_idle' => count($idleMembers),
            ],
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
        $users  = [];

        foreach ($members as $member) {
            $parts = explode('|', $member, 6);
            if (count($parts) < 3) continue;

            [$userId, $platform, $socketId, $ip, $os, $version] = $parts;
           
            if ($platform === 'spa')       $spaCount++;
            elseif ($platform === 'tvapk') $tvCount++;

            $users[$userId] = [
            'ip'          => $ip,
            'os'          => $os,
            'apk_version' => $version,
            'platform'    => $platform,
        ];        }

        return [
            'total'       => count($members),
            'by_platform' => ['spa' => $spaCount, 'tvapk' => $tvCount],
            'users'    => $users??[],
        ];
    }
}