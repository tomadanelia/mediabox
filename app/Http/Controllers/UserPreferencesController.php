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
        $user=User::findOrFail($request->userId);
        $favouriteChannelIds=$user->favouriteChannels()->pluck('external_id');
        return response()->json([
            'favouriteChannelIds'=>$favouriteChannelIds
        ]);
    
    }
}
