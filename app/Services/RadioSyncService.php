<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RadioSyncService
{
    private string $baseUrl = 'https://222.mediabox.ge/webapi';

    private function getHeaders(): array
    {
        return [
            'Origin' => 'https://222.mediabox.ge',
            'Referer' => 'https://222.mediabox.ge/',
            'Accept' => 'application/json',
            'User-Agent' => 'PostmanRuntime/7.51.0',
        ];
    }

    public function fetchRadioChannels(): array
    {
        $response = Http::withoutVerifying()
            ->withHeaders($this->getHeaders())
            ->post($this->baseUrl, [
                'Method' => 'GetRadioChannelList',
                'urltype' => 'flussonic'
            ]);

        return $response->successful() ? $response->json() : [];
    }

    public function getRadioStream(string $externalId, string $clientIp): ?array
    {
        $response = Http::withoutVerifying()
            ->withHeaders($this->getHeaders())
            ->post($this->baseUrl, [
                'Method' => 'GetRadioLiveStream',
                'Pars' => ['CHANNEL_ID' => (int)$externalId],
                'urltype' => 'AndroidSTB',
                'clientip' => $clientIp,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return ($data['result'] === 'success') ? $data['data'] : null;
        }

        return null;
    }
}