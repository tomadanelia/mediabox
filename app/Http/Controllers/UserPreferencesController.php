<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Channel;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserPreferencesController
{
    public function getFavouriteChannels(Request $request):JsonResponse
    {
        $favouriteChannelIds=$request->user()->favouriteChannels()->pluck('external_id');
        return response()->json([
            'favouriteChannelIds'=>$favouriteChannelIds
        ]);
    
    }
    public function addFavouriteChannel(Request $request):JsonResponse
    {
        $channel = Channel::where('external_id', $request->channelId)->firstOrFail();
        $request->user()->favouriteChannels()->syncWithoutDetaching([$channel->id]);
        return response()->json([
            'message'=>'Channel added to favourites successfully'
        ]);
    }
    public function removeFavouriteChannel(Request $request,$channelId):JsonResponse
    {
        $channel = Channel::where('external_id',$channelId)->firstOrFail();
        $request->user()->favouriteChannels()->detach($channel->id);
        return response()->json([
            'message'=>'Channel removed from favourites successfully'
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
