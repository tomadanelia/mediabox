<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

class RemoteController extends Controller
{
  
    public function tvReady(Request $request): JsonResponse
    {
        $request->validate(['device_id' => 'required|string']);

        $device = UserDevice::where('device_id', $request->device_id)->first();
        if (!$device) {
            return response()->json(['message' => 'Device not paired'], 403);
        }
        Cache::put("tv_session_ready:{$device->device_id}", true, 1800);

        return response()->json([
            'status' => 'waiting_for_phone',
           'channel' => "tv.remote.{$device->device_id}",
            'reverb_config' => [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
            ]
        ]);
    }
    //spa route
    public function getMyDevices(Request $request): JsonResponse
    {
        $devices = $request->user()->devices->map(function ($device) {
            return [
                'device_id' => $device->device_id,
                'name' => $device->device_name,
                'is_ready' => Cache::has("tv_session_ready:{$device->device_id}"),
                'channel' => "tv.remote.{$device->device_id}",
            ];
        });

        return response()->json($devices);
    }
}