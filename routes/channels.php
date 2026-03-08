<?php
use Illuminate\Support\Facades\Broadcast;
use App\Models\UserDevice;

Broadcast::channel('tv.remote.{deviceId}', function ($user, $deviceId) {
    return UserDevice::where('user_id', $user->id)
                     ->where('device_id', $deviceId)
                     ->exists();
});