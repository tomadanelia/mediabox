<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TokenTtlController extends Controller
{
    public function getTokenSettings(): JsonResponse
    {
        $keys = [
            'flussonic_live_lifetime', 
            'flussonic_archive_lifetime', 
        ];
        
        $settings = SiteSetting::whereIn('key', $keys)->pluck('value', 'key');
        
        return response()->json([
            'live_token_lifetime_seconds' => (int) ($settings['flussonic_live_lifetime'] ?? 14400),
            'archive_token_lifetime_seconds' => (int) ($settings['flussonic_archive_lifetime'] ?? 3600),
        ]);
    }

    public function updateTokenSettings(Request $request): JsonResponse
    {
        $request->validate([
            'live_token_ttl'    => 'required|integer|min:60',
            'archive_token_ttl' => 'required|integer|min:60',
        ]);

        SiteSetting::updateOrCreate(['key' => 'flussonic_live_lifetime'], ['value' => $request->live_token_ttl]);
        SiteSetting::updateOrCreate(['key' => 'flussonic_archive_lifetime'], ['value' => $request->archive_token_ttl]);

        Cache::forget('setting_live');
        Cache::forget('setting_archive');
        return response()->json(['message' => 'Token TTLs updated and cache purged.']);
    }
}