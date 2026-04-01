<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Channel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
class UserPreferencesController extends Controller
{
    public function getFavouriteChannels(Request $request): JsonResponse
{
    $deviceId = $request->query('device_id', 'spa_web');
    $userId = $request->user()->id;

    $cacheKey = "user_favs_{$userId}_{$deviceId}";

    $favouriteChannelIds = Cache::remember($cacheKey, 3600, function() use ($userId, $deviceId) {
        return DB::table('user_favourites')
            ->join('channels', 'user_favourites.channel_id', '=', 'channels.id')
            ->where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->pluck('channels.external_id');
    });

    return response()->json([
        'favouriteChannelIds' => $favouriteChannelIds
    ]);
}
    public function addFavouriteChannel(Request $request): JsonResponse
{
    $request->validate([
        'channelId' => 'required|exists:channels,external_id',
        'device_id' => 'sometimes|string' 
    ]);

    $deviceId = $request->input('device_id', 'spa_web');
    $channel = Channel::where('external_id', $request->channelId)->firstOrFail();
    $userId = $request->user()->id;

    DB::table('user_favourites')->updateOrInsert(
        ['user_id' => $userId, 'channel_id' => $channel->id, 'device_id' => $deviceId],
        ['updated_at' => now()]
    );

    Cache::forget("user_favs_{$userId}_{$deviceId}");

    return response()->json(['message' => 'Channel added successfully']);
}
    public function removeFavouriteChannel(Request $request, $channelId): JsonResponse
{
    $deviceId = $request->query('device_id', 'spa_web');
    $user = $request->user();

    $channel = Channel::where('external_id', $channelId)->firstOrFail();

    DB::table('user_favourites')
        ->where('user_id', $user->id)
        ->where('channel_id', $channel->id)
        ->where('device_id', $deviceId)
        ->delete();

    Cache::forget("user_favs_{$user->id}_{$deviceId}");

    return response()->json([
        'message' => 'Channel removed from favourites for this device'
    ]);
}
    public function updateWatchHistory(Request $request):JsonResponse
    {
        $request->validate([
            'channelId' => 'required|exists:channels,external_id',
        ]);
        $channel = Channel::where('external_id', $request->channelId)->firstOrFail();
        $request->user()->watchHistories()->create(
           [
           'channel_id' => $channel->id,
           'watched_at' => now(),
           ]
        );
        return response()->json([
            'message'=>'Watch history updated successfully'
        ]);
    }
    //i will change to returning external ids instead of internal ones to avoid confusion on client side if ther is one
    public function getLastviewedChannels(Request $request):JsonResponse{
    $lastChannelIds = $request->user()
    ->watchHistories()
    ->select('channel_id')
    ->latest('watched_at')
    ->distinct('channel_id') 
    ->take(10)
    ->pluck('channel_id');

    return response()->json([
    'lastViewedChannels' => $lastChannelIds
   ]);

    }
    
}
