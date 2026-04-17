<?php

namespace App\Services;
use GeoIp2\Database\Reader;
class FlussonicTokenService
{
    /**
     * Called directly when you have stream + server already parsed.
     */
    public function generateTokenData(string $stream, string $server, string $clientIp): array
    {
        $serverLower = strtolower($server);
        $key = (str_starts_with($serverLower, 's11') || str_starts_with($serverLower, 's12'))
            ? config('services.flussonic.key_special')
            : config('services.flussonic.key_default');

        $lifetime  = 4 * 60 * 60;
        $desync    = 300;
        $starttime = time() - $desync;
        $endtime   = $starttime + $desync + $lifetime;

        $salt    = bin2hex(random_bytes(16));
        $hashStr = $stream . $clientIp . $starttime . $endtime . $key . $salt;
        $hash    = sha1($hashStr);
        $token   = $hash . '-' . $salt . '-' . $endtime . '-' . $starttime;

        $ccn     = $this->resolveCountryCode($clientIp);
        $outHost = $this->resolveOutHost($ccn, $clientIp);

        return [
            'token'        => $token,
            'url'          => "$outHost/$server",
            'channel'      => '/' . $stream,
            'starttime'    => (string) $starttime,
            'endtime'      => (string) $endtime,
            'client_ip'    => $clientIp,
            'client_cc'    => $ccn,
            'hls'          => '/index.m3u8',
            'mpegts'       => 'mpegts',
            'hash'         => $hash,
            'salt'         => $salt,
            'full_hls'     => "$outHost/$server/$stream/index.m3u8?token=$token",
            'full_mpegts'  => "$outHost/$server/$stream?token=$token",
        ];
    }

    /**
     * Called by SyncingService in production.
     * Parses a legacy template URL like:
     *   http://192.168.34.51:8008/getlink_json.php?channel=tv/rustavi2
     * and extracts stream + server to generate a token locally.
     *
     * The "server" segment is the first path component of the channel param,
     * e.g. channel=tv/rustavi2 → server="tv", stream="rustavi2"
     * Adjust the parsing below if your naming convention differs.
     */
    public function fromTemplateUrl(string $templateUrl, string $clientIp): array
    {
        $query = [];
        parse_str(parse_url($templateUrl, PHP_URL_QUERY), $query);

        // channel param looks like "tv/rustavi2" or "s1/pirveli_arkhi"
        $channel = $query['channel'] ?? '';
        $parts   = explode('/', ltrim($channel, '/'), 2);

        $server = $parts[0] ?? 'default';
        $stream = $parts[1] ?? $channel;

        return $this->generateTokenData($stream, $server, $clientIp);
    }

    /**
     * Same as fromTemplateUrl but builds archive URLs.
     * Returns an array shaped like SyncingService::getArchiveUrl expects.
     */
    public function fromTemplateUrlForArchive(string $templateUrl, string $clientIp, int $startEpoch): array
    {
        $base = $this->fromTemplateUrl($templateUrl, $clientIp);

        $outHost = parse_url($base['url'], PHP_URL_SCHEME) . '://' . parse_url($base['url'], PHP_URL_HOST);
        $server  = ltrim(parse_url($base['url'], PHP_URL_PATH), '/');
        $stream  = ltrim($base['channel'], '/');
        $token   = $base['token'];

        return [
            'url'    => "$outHost/$server/$stream/video-timeshift_abs-{$startEpoch}.m3u8?token=$token",
            'length' => 0, // not available locally; enrich from DB if needed
        ];
    }

    private function resolveCountryCode(string $ip): string
{
    try {
        $reader = new \GeoIp2\Database\Reader('/var/www/geoip/GeoLite2-Country.mmdb');
        $record = $reader->country($ip);
        return $record->country->isoCode;
    } catch (\Exception $e) {
        return '';
    }
}

    private function resolveOutHost(string $ccn, string $clientIp): string
    {
        if ($clientIp === '71.255.49.44') {
            return 'http://192.168.38.201';
        }
        return ($ccn === '' || $ccn === 'GE')
            ? config('services.flussonic.proxy_ge')
            : config('services.flussonic.proxy_global');
    }
}