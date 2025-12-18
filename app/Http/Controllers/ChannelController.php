<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChannelController extends Controller
{

    public function index(): JsonResponse
    {
        $channels = Channel::where('is_active', true)->get();

        return response()->json([
            'status' => 'success',
            'count' => $channels->count(),
            'data' => $channels
        ], 200);
    }
}