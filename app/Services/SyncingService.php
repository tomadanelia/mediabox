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
        'Content-Type' => 'application/json',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ];

    /**
     * Getting raw channelList for Synchronization
     */
    public function fetchChannelList(): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->post($this->baseUrl, [
                    'Method' => 'GetChannelList'
                ]);

            if ($response->ok()) {
                return $response->json();
            }
            
            Log::error('MediaBox API Error: ' . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error('MediaBox Connection Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Gets livestream url from cache or fetches it from MediaBox API and caches it for 3.5 hours
     * params: externalId - Channel external ID
     * return: array|null - ['url' => string, 'expires_at' => int, 'server_time' => int] or null on failure
     */
    public function getStreamUrl(string $externalId): ?array
    {
        $key = "channel_stream_{$externalId}";

        return Cache::remember($key, 12600, function () use ($externalId) {
            $response = Http::withHeaders($this->headers)
                ->post($this->baseUrl, [
                    'Method' => 'GetLiveStream',
                    'Pars' => ['CHANNEL_ID' => (int)$externalId],
                    'urltype' => 'flussonic'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'url' => $data['URL'] ?? null,
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