<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Channel;

class StatsController extends Controller
{
    private const TTL =120; 

    /**
     * METRICS: Called by the Statistics Server (Zabbix/Prometheus).
     * GET /api/metrics/realtime
     */
    
 
    public function getMetrics(Request $request)
    {
        $now    = time();
        $cutoff = $now - self::TTL;
 
        $channels = Channel::select('external_id', 'name_en')->get();
 
        $counts = Redis::pipeline(function ($pipe) use ($channels, $cutoff) {
            $pipe->zremrangebyscore('active_viewers:global', 0, $cutoff);
            $pipe->zcard('active_viewers:global');
 
            foreach ($channels as $channel) {
                $pipe->zremrangebyscore("active_viewers:{$channel->external_id}", 0, $cutoff);
                $pipe->zcard("active_viewers:{$channel->external_id}");
            }
        });
        $globalCount = $counts[1];
 
        $channelData = [];
        foreach ($channels as $index => $channel) {
            $channelData[$channel->external_id] = [
                'name'    => $channel->name_en,
                'viewers' => $counts[($index * 2) + 3],
            ];
        }
 
        return response()->json([
            'timestamp'            => $now,
            'total_online_devices' => $globalCount,
            'channels'             => $channelData,
        ]);
    }
}