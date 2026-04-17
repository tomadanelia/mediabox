<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\Channel;

class SyncingService
{
    private string $baseUrl = 'https://222.mediabox.ge/webapi';
    private array $headers = [
        'Origin' => 'https://222.mediabox.ge',
        'Accept' => 'application/json',
    ];

    public function __construct(private FlussonicTokenService $tokenService) {}

    /**
     * Entry point for Live Streams
     */
     public function getStreamUrl(string $externalId, string $clientIp): ?array
    {
        $cacheKey = "channel_stream_{$externalId}_{$clientIp}";

        return Cache::remember($cacheKey, 300, function () use ($externalId, $clientIp) {
                $local = $this->getStreamUrlLocal($externalId, $clientIp);
                if ($local) return $local;
                return null;

        });
    }
    /**
     * Entry point for Archive Streams
     */
    public function getArchiveUrl(string $externalId, int $startEpoch, string $clientIp): ?array
    {
        $cacheKey = "channel_archive_{$externalId}_{$startEpoch}_{$clientIp}";

        return Cache::remember($cacheKey, 300, function () use ($externalId, $startEpoch, $clientIp) {
                $local = $this->getArchiveUrlLocal($externalId, $startEpoch, $clientIp);
                if ($local) return $local;
                return null;
        });
    }
private function getStreamUrlLegacy(string $externalId, string $clientIp): ?array
    {
        $response = Http::withoutVerifying()->withHeaders([
            'Origin' => 'https://222.mediabox.ge',
            'Referer' => 'https://222.mediabox.ge/',
            'User-Agent' => 'PostmanRuntime/7.51.0',
        ])->post($this->baseUrl, [
            'Method' => 'GetLiveStream',
            'Pars'   => ['CHANNEL_ID' => (int)$externalId],
            'urltype' => 'flussonic',
            'clientip' => $clientIp,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['URL'])) {
                return [
                    'url'        => $data['URL'], 
                    'expires_at' => $data['END'] ?? null,
                ];
            }
        }
        return null;
    }

    private function getStreamUrlLocal(string $externalId, string $clientIp): ?array
    {
        $source = $this->getLiveSource($externalId);
        if (!$source) return null;

        $tokenData = $this->tokenService->fromTemplateUrl($source->channel_url, $clientIp);

        return [
            'url'        => $tokenData['full_hls'],
            'expires_at' => $tokenData['endtime'],
        ];
    }

    private function getArchiveUrlLegacy(string $externalId, int $startEpoch, string $clientIp): ?array
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
                
                return [
                    'url'    => "{$parsed['scheme']}://{$parsed['host']}{$basePath}/{$timeshiftFile}?{$query}",
                    'length' => $baseData['ARCHIVE_LENGTH'] ?? 0,
                ];
            }
        }
        return null;
    }

    private function getArchiveUrlLocal(string $externalId, int $startEpoch, string $clientIp): ?array
    {
        $source = $this->getArchiveSource($externalId);
        if (!$source) return null;

        $archiveData = $this->tokenService->fromTemplateUrlForArchive($source->channel_url, $clientIp, $startEpoch);

        return [
            'url'    => $archiveData['url'],
            'length' => $source->archive_length ?? 168, 
        ];
    }

    private function isProduction(): bool
    {
        return config('app.env') === 'production';
    }

    private function getLiveSource(string $externalId)
    {
        return Channel::where('external_id', $externalId)
            ->first()
            ?->streamUrls()
            ->first();
    }

    private function getArchiveSource(string $externalId)
    {
        return Channel::where('external_id', $externalId)
            ->first()
            ?->archiveUrls()
            ->first();
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
public function getDownloadUrl(string $externalId, int $startEpoch): ?string
{
    $serverIp = '159.89.20.100';
    
    $response = Http::withoutVerifying()->withHeaders([
        'Origin' => 'https://222.mediabox.ge',
        'Referer' => 'https://222.mediabox.ge/',
        'User-Agent' => 'PostmanRuntime/7.51.0',
    ])->post($this->baseUrl, [
        'Method' => 'GetArchiveStream',
        'Pars' => [
            'CHANNEL_ID' => (int) $externalId,
            'clientip'   => $serverIp,
        ],
        'urltype'  => 'flussonic',
        'clientip' => $serverIp,
    ]);
    \Log::info('getDownloadUrl response', [
    'status' => $response->status(),
    'body'   => $response->body(),
]);
    if ($response->successful()) {
        $data = $response->json();
        if (!empty($data['URL'])) {
            $rawUrl    = $data['URL'];
            $parsed    = parse_url($rawUrl);
            $pathParts = explode('/', $parsed['path']);
            array_pop($pathParts);
            $basePath  = implode('/', $pathParts);

            return "{$parsed['scheme']}://{$parsed['host']}{$basePath}/video-timeshift_abs-{$startEpoch}.m3u8?{$parsed['query']}";
        }
    }
    return null;
}
}