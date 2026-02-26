<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SyncingService
{
    private string $baseUrl = 'https://222.mediabox.ge/webapi';
    private array $headers = [
        'Origin' => 'https://222.mediabox.ge',
        'Accept' => 'application/json',
    ];

   public function getStreamUrl(string $externalId, string $clientIp): ?array
{
    $key = "channel_stream_{$externalId}_{$clientIp}";

    return Cache::remember($key, 300, function () use ($externalId, $clientIp) {
          $response = Http::withoutVerifying()->withHeaders([
        'Origin' => 'https://222.mediabox.ge',
        'Referer' => 'https://222.mediabox.ge/',
        'User-Agent' => 'PostmanRuntime/7.51.0',
    ])->post($this->baseUrl, [
        'Method' => 'GetLiveStream',
        'Pars' => [
            'CHANNEL_ID' => (int)$externalId,
        ],
        'urltype' => 'flussonic',
        'clientip'   => $clientIp,
    ]);

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['URL'])) {
                return [
                    'url' => $data['URL'], 
                    'expires_at' => $data['END'] ?? null,
                ];
            }
        }
        return null;
    });
}

public function getArchiveUrl(string $externalId, int $startEpoch, string $clientIp): ?array
{
    $response = Http::withoutVerifying()->withHeaders([
        'Origin' => 'https://222.mediabox.ge',
        'Referer' => 'https://222.mediabox.ge/',
        'User-Agent' => 'PostmanRuntime/7.51.0',
    ])->post($this->baseUrl, [
        'Method' => 'GetArchiveStream',
        'Pars' => [
            'CHANNEL_ID' => (int) $externalId,
            'clientip'   => $clientIp
        ],
        'urltype' => 'flussonic',
        'clientip'   => $clientIp,

    ]);

    if ($response->successful()) {
        $baseData = $response->json();
        if (!empty($baseData['URL'])) {
            $rawUrl = $baseData['URL']; 
            $parsed = parse_url($rawUrl);
            $pathParts = explode('/', $parsed['path']);
            array_pop($pathParts); 
            $basePath = implode('/', $pathParts);

            $timeshiftFile = "video-timeshift_abs-{$startEpoch}.m3u8";
            $query = $parsed['query'] ?? '';
            
            $finalUrl = "{$parsed['scheme']}://{$parsed['host']}{$basePath}/{$timeshiftFile}?{$query}";

            return [
                'url' => $finalUrl,
                'length'=> $baseData['ARCHIVE_LENGTH'] ?? 0,
            ];
        }
    }
    return null;
}
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
    public function getAllEpg(string $externalId, int $start,int $end): array
{
    $key = "channel_all_epg_{$externalId}_{$start}_{$end}";

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

}