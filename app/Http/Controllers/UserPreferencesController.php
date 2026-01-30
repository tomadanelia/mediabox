<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserPreferencesController
{
    public function GetFavouriteChannels(Request $request):JsonResponse
    {
        $favouriteChannelIds=$request->user()->favouriteChannels()->pluck('external_id');
        return response()->json([
            'favouriteChannelIds'=>$favouriteChannelIds
        ]);
    
    }
    public function AddFavouriteChannel(Request $request):JsonResponse
    {
        $channel = Channel::where('external_id', $request->channelId)->firstOrFail();
        $request->user()->favouriteChannels()->toggle([$channel->id]);
        return response()->json([
            'message'=>'Channel added to favourites successfully'
        ]);
    }
}
