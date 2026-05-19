<?php

namespace App\Services;

use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\Log;

class GeoLocationService
{
    public function getCountryCode(?string $ip): string
    {
        if (!$ip || $ip === '127.0.0.1') {
            return 'GE'; 
        }

        try {
            $reader = new Reader('/var/www/geoip/GeoLite2-Country.mmdb');
            $record = $reader->country($ip);
            return $record->country->isoCode ?? 'GE';
        } catch (\Exception $e) {
            Log::error("GeoIP Error: " . $e->getMessage());
            return 'GE'; 
        }
    }

    public function isInternational(string $ip): bool
    {
        return $this->getCountryCode($ip) !== 'GE';
    }
    public function getEffectiveScope(string $ip): string
{
    return cache()->remember("ip_scope_{$ip}", 3600, function() use ($ip) {
        $code = $this->getCountryCode($ip);
        return ($code === 'GE') ? 'ge' : 'intl';
    });
}
}