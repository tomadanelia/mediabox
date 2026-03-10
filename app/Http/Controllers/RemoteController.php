<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserDevice;
use App\Services\SocketTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

class RemoteController extends Controller
{
    public function __construct(protected SocketTokenService $tokenService) {}

    public function tvReady(Request $request): JsonResponse
    {
        $request->validate(['device_id' => 'required|string']);

        $device = UserDevice::where('device_id', $request->device_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$device) {
            return response()->json(['message' => 'Unauthorized device'], 403);
        }

        Cache::put("tv_session_ready:{$device->device_id}", true, 1800);

        return response()->json([
            'status' => 'waiting_for_phone',
            'socket_token' => $this->tokenService->generateToken($request->user()->id, $device->device_id),
            'device_id' => $device->device_id
        ]);
    }

    public function getMyDevices(Request $request): JsonResponse
    {
        $devices = $request->user()->devices->map(function ($device) use ($request) {
            $isReady = Cache::has("tv_session_ready:{$device->device_id}");
            return [
                'device_id' => $device->device_id,
                'name' => $device->device_name,
                'is_ready' => $isReady,
                'socket_token' => $isReady ? $this->tokenService->generateToken($request->user()->id, $device->device_id) : null
            ];
        });

        return response()->json($devices);
    }
}