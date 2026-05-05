<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\ChannelUrl;
use App\Models\ChannelArchiveUrl;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AdminChannelUrlController extends Controller
{
    public function index($external_id): JsonResponse
    {
        $channel = Channel::where('external_id', $external_id)
            ->with(['streamUrls', 'archiveUrls'])
            ->firstOrFail();

        return response()->json([
            'channel_name' => $channel->name,
            'live_urls'    => $channel->streamUrls,
            'archive_urls' => $channel->archiveUrls,
        ]);
    }

    /**
     * Add a Live Stream URL
     */
    public function storeLive(Request $request, $external_id): JsonResponse
    {
        $request->validate([
            'channel_url' => 'required|string|max:2083',
            'url_type'    => 'nullable|integer',
            'filter'      => 'nullable|string|max:20',
            'priority'    => 'nullable|integer',
        ]);

        $channel = Channel::where('external_id', $external_id)->firstOrFail();

        $url = $channel->streamUrls()->create($request->all());
        $this->clearChannelCaches($external_id);
        return response()->json(['message' => 'Live URL added', 'data' => $url], 201);
    }

    /**
     * Add an Archive URL
     */
    public function storeArchive(Request $request, $external_id): JsonResponse
    {
        $request->validate([
            'channel_url'    => 'required|string|max:2083',
            'url_type'       => 'nullable|integer',
            'archive_length' => 'nullable|integer', 
            'priority'       => 'nullable|integer',
        ]);

        $channel = Channel::where('external_id', $external_id)->firstOrFail();

        $url = $channel->archiveUrls()->create($request->all());
        $this->clearChannelCaches($external_id);
        return response()->json(['message' => 'Archive URL added', 'data' => $url], 201);
    }

    /**
     * Delete a Live URL
     */
    public function destroyLive($external_id, $id): JsonResponse
    {
        ChannelUrl::where('id', $id)
            ->where('channel_id', $external_id)
            ->delete();

        $this->clearChannelCaches($external_id);
        return response()->json(['message' => 'Live URL deleted']);
    }


    public function destroyArchive($external_id, $id): JsonResponse
    {
        ChannelArchiveUrl::where('id', $id)
            ->where('channel_id', $external_id)
            ->delete();

        $this->clearChannelCaches($external_id);
        return response()->json(['message' => 'Archive URL deleted']);
    }

public function updateLive(Request $request, $external_id, $id): JsonResponse
{
    $request->validate([
        'channel_url' => 'required|string|max:2083',
        'url_type'    => 'nullable|integer',
        'priority'    => 'nullable|integer',
    ]);

    $url = ChannelUrl::where('id', $id)->where('channel_id', $external_id)->firstOrFail();
    $url->update($request->all());

    $this->clearChannelCaches($external_id);

    return response()->json(['message' => 'Live URL updated', 'data' => $url]);
}

/**
 * Update an existing Archive URL
 */
public function updateArchive(Request $request, $external_id, $id): JsonResponse
{
    $request->validate([
        'channel_url'    => 'required|string|max:2083',
        'archive_length' => 'nullable|integer',
        'priority'       => 'nullable|integer',
    ]);

    $url = ChannelArchiveUrl::where('id', $id)->where('channel_id', $external_id)->firstOrFail();
    $url->update($request->all());

    $this->clearChannelCaches($external_id);

    return response()->json(['message' => 'Archive URL updated', 'data' => $url]);
}

/**
 * Helper to clear caches so users see the new URLs immediately
 */
private function clearChannelCaches($externalId)
{
    $redis = \Illuminate\Support\Facades\Redis::connection();
    $prefix = config('cache.prefix');
    
    $patterns = [
        $prefix . "channel_stream_{$externalId}_*",
        $prefix . "channel_archive_{$externalId}_*"
    ];

    foreach ($patterns as $pattern) {
        $keys = $redis->keys($pattern);
        foreach ($keys as $key) {
            $redis->del(str_replace($prefix, '', $key));
        }
    }
}
}