<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Models\Channel;
use App\Models\User;

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

public function getUserDetails(Request $request, $userId)
{
    $token = $request->header('X-Stats-Token') ?? $request->query('token');
    if ($token !== config('services.flussonic.stats_token')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $user = User::where('id', $userId)
        ->orWhere('numeric_id', $userId)
        ->with(['account', 'subscriptionPlans' => function($query) {
            $query->wherePivot('is_active', true)
                  ->wherePivot('expires_at', '>', now());
        }])
        ->first();

    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    return response()->json([
        'user_info' => [
            'id' => $user->id,
            'numeric_id' => $user->numeric_id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'created_at' => $user->created_at->toDateTimeString(),
        ],
        'account' => [
            'balance' => $user->account?->balance ?? 0,
        ],
        'active_packets' => $user->subscriptionPlans->map(function ($plan) {
            return [
                'plan_id' => $plan->id,
                'name_en' => $plan->name_en,
                'name_ka' => $plan->name_ka,
                'price' => $plan->price,
                'expires_at' => $plan->pivot->expires_at->toDateTimeString(),
                'days_remaining' => now()->diffInDays($plan->pivot->expires_at, false),
                'auto_renew' => (bool) $plan->pivot->auto_renew,
            ];
        }),
    ]);
}
}