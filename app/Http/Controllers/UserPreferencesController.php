<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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
    
}
