<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

        dump('Request URL: ' . $this->baseUrl);
        dump('Request Body: ' . json_encode(['Method' => 'GetChannelList']));
        dump('Response Status: ' . $response->status());
        dump('Response Headers: ' . json_encode($response->headers()));
        dump('Response Body: ' . $response->body());

        if ($response->successful()) {
            return $response->json();
        }
        
        dump('API Error: ' . $response->status() . ' - ' . $response->body());
        return [];
    } catch (\Exception $e) {
        dump('Exception: ' . $e->getMessage());
        dump('Exception Trace: ' . $e->getTraceAsString());
        return [];
    }
}
    /**
     * Gets livestream url from cache or fetches it from MediaBox API adds  returns and caches it for 1 hour
     * params: externalId - Channel external ID
     * return: array|null - ['url' => string, 'expires_at' => int, 'server_time' => int] or null on failure
     */
    public function getStreamUrl(string $externalId): ?array
    {
        $key = "channel_stream_{$externalId}";

        return Cache::remember($key, 3600, function () use ($externalId) {
            $response = Http::withoutVerifying()->withHeaders($this->headers)
                ->post($this->baseUrl, [
                    'Method' => 'GetLiveStream',
                    'Pars' => ['CHANNEL_ID' => (int)$externalId],
                    'urltype' => 'flussonic'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $rawUrl = $data['URL'] ?? null;

                if (!$rawUrl) {
                    return null;
                }
                 $appUrl = config('app.url');
                 $proxyUrl = $appUrl . '/stream-proxy/' . $rawUrl;

                return [
                    'url' => $proxyUrl,
                    'expires_at' => $data['END'] ?? null,
                    'server_time' => now()->timestamp
                ];
            }

            return null;
        });
    }

    /**
     * Get EPG (Cached for 1 hour)
     * MOCKED waiting for akaki's API
     */
    public function getEpg(string $externalId, string $date): array
    {
        $key = "channel_epg_{$externalId}_{$date}";

        return Cache::remember($key, 3600, function () use ($externalId, $date) {
            return [
                ['time' => '20:00', 'title' => 'Program Data Pending', 'duration' => 60]
            ];
        });
    }

    /**
     * Get Archive URL (Cached for 6 hours)
     * PLACEHOLDER -  MOCKED waiting for akaki's API
     */
    public function getArchiveUrl(string $externalId, int $timestamp): ?array
    {
        
        return [
            'url' => "https://proxy.streamer.mediabox.ge/archive/{$externalId}/{$timestamp}.m3u8",
            'start' => $timestamp
        ];
    }
}