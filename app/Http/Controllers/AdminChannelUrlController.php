<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\ChannelUrl;
use App\Models\ChannelArchiveUrl;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

        return response()->json(['message' => 'Live URL deleted']);
    }

    /**
     * Delete an Archive URL
     */
    public function destroyArchive($external_id, $id): JsonResponse
    {
        ChannelArchiveUrl::where('id', $id)
            ->where('channel_id', $external_id)
            ->delete();

        return response()->json(['message' => 'Archive URL deleted']);
    }
}