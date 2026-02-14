<?php

namespace App\Services;

use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncingService
{
    private string $baseUrl = 'https://222.mediabox.ge/webapi';
    private array $headers = [
        'Origin' => 'https://222.mediabox.ge',
        'Accept' => 'application/json',
    ];

    /**
     * Getting raw channelList for Synchronization
     */
   public function fetchChannelList(): array
{
    try {
        $response = Http::withoutVerifying()->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
            'Origin' => 'https://222.mediabox.ge',
            'Referer' => 'https://222.mediabox.ge/',
            'User-Agent' => 'PostmanRuntime/7.51.0', 
        ])->post($this->baseUrl, [
            'Method' => 'GetChannelList'
        ]);
        if ($response->successful()) {
            return $response->json();
        }
        
        return [];
    } catch (\Exception $e) {
        return [];
    }
}
    /**
     * Gets livestream url from cache or fetches it from MediaBox API adds  returns and caches it for 1 hour
     * params: externalId - Channel external ID
     * return: array|null - ['url' => string, 'expires_at' => int, 'server_time' => int] or null on failure
     */
    public function getStreamUrl(string $externalId,bool $isFree): ?array
    {
        $key = "channel_stream_{$externalId}";

        return Cache::remember($key, 3600, function () use ($externalId,$isFree) {
            $response = Http::withoutVerifying()->withHeaders($this->headers)
                ->post($this->baseUrl, [
                    'Method' => 'GetLiveStream',
                    'Pars' => ['CHANNEL_ID' => (int)$externalId],
                    'urltype' => 'flussonic'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $rawUrl = $data['URL'] ?? null;

                if ($rawUrl) {
                $parsed = parse_url($rawUrl);
                $pathAndQuery = $parsed['path'] . ($parsed['query'] ? '?' . $parsed['query'] : '');
                if ($isFree) {
                 $proxyUrl = config('app.url') . '/stream-free/' . ltrim($pathAndQuery, '/');
                 } else {
                 $proxyUrl = config('app.url') . '/stream-premium/' . ltrim($pathAndQuery, '/');
                 }

                return [
                    'url' => $proxyUrl,
                    'expires_at' => $data['END'] ?? null,
                    'server_time' => now()->timestamp
                ];
                }
            }

            return null;
        });
    }//i have to fix cache ttl to make it same as token end time

    /**
     * Get EPG (Cached for 1 hour)
     */
    public function getEpg(string $externalId, string $date): array
{
    $start = Carbon::parse($date)->startOfDay()->timestamp;
    $end   = Carbon::parse($date)->endOfDay()->timestamp;

    $key = "channel_epg_{$externalId}_{$start}_{$end}";

    return Cache::remember($key, 3600, function () use ($externalId, $start, $end) {

        $response = Http::withoutVerifying()->withHeaders($this->headers)
        ->post('https://222.mediabox.ge/webapi', [
            'Method' => 'LoadEpgData',
            'Pars' => [
                'CHANNEL_ID' => (int) $externalId,
                'TIME_START' => $start,
                'TIME_END'   => $end,
            ],
        ]);

       if (!$response->successful()) {
            return []; 
        }

        try {
            $data = $response->json(); 
            return is_array($data) ? $data : []; 
        } catch (\Illuminate\Http\Client\RequestException $e) {
            return []; 
        }
    });
}

    /**
     * Get Archive URL (Cached for 6 hours)
     * PLACEHOLDER -  MOCKED waiting for akaki's API
     */
    public function getArchiveUrl(string $externalId,int $startEpoch,bool $isFree): ?array
    {
     $baseData = Cache::remember("channel_archive_base_{$externalId}", 3000, function () use ($externalId,$isFree) {
        $response = Http::withoutVerifying()->withHeaders([
         'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
            'Origin' => 'https://222.mediabox.ge',
            'Referer' => 'https://222.mediabox.ge/',
            'User-Agent' => 'PostmanRuntime/7.51.0', 
    ])->post('https://222.mediabox.ge/webapi', [
        'Method' => 'GetArchiveStream',
        'Pars' => [
            'CHANNEL_ID' => (int) $externalId,
        ],
        'urltype' => 'flussonic',
    ]);
      if (!$response->successful()) {
        return null;
        }
         return $response->json();
     });
     if (!$baseData || empty($baseData['URL'])) {
            return null;
        }
        $dateTodayEpoch = Carbon::now()->timestamp;
        $archiveLength = $baseData['ARCHIVE_LENGTH'] ?? 0;
        if ($dateTodayEpoch-$startEpoch>$archiveLength*3600) {
            return null;
        }
        $rawUrl = $baseData['URL']; 
        $parsed = parse_url($rawUrl);
        $pathParts = explode('/', $parsed['path']);
        array_pop($pathParts); 
        $basePath = implode('/', $pathParts);

        $timeshiftFile = "video-timeshift_abs-{$startEpoch}.m3u8";
        
        $newPath = $basePath . '/' . $timeshiftFile;

        $query = $parsed['query'] ?? '';
        $proxyPath = $newPath . ($query ? '?' . $query : '');
        if($isFree){
        $finalUrl = config('app.url') . '/archive-free' . $proxyPath;
        }else{
        $finalUrl = config('app.url') . '/archive-premium' . $proxyPath;
        }

        return [
            'url' => $finalUrl,
            'length'=> $baseData['ARCHIVE_LENGTH'] ?? 0,
        ];
    }
}