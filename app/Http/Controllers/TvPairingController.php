<?php
namespace App\Http\Controllers;

use App\Models\TvPairing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\UserDevice;


class TvPairingController extends Controller
{
    public function initialize(Request $request, SocketTokenService $socketService)
{
    $request->validate(['device_id' => 'required|string']);

    $code = strtoupper(Str::random(6));
    
    TvPairing::create([
        'pairing_code' => $code,
        'device_id' => $request->device_id,
        'expires_at' => now()->addMinutes(10),
    ]);

    $socketToken = $socketService->generateToken(
        'device_' . $request->device_id,
        "pairing_{$code}"
    );

    return response()->json([
        'pairing_code' => $code,
        'socket_token' => $socketToken
    ]);
}

public function pair(Request $request)
{
    $request->validate(['pairing_code' => 'required|string']);

    $pairing = TvPairing::where('pairing_code', $request->pairing_code)
        ->where('expires_at', '>', now())
        ->firstOrFail();

    $claimToken = Str::random(64);
    $pairing->update([
        'user_id' => $request->user()->id,
        'claim_token' => hash('sha256', $claimToken)
    ]);
    Redis::publish('pairing_events', json_encode([
        'pairing_code' => $request->pairing_code,
        'status' => 'ready_to_claim',
        'claim_token' => $claimToken 
    ]));

    return response()->json(['message' => 'Authorized. TV notified.']);
}

public function claim(Request $request)
{
    $request->validate([
        'claim_token' => 'required|string',
        'device_id' => 'required|string'
    ]);

    $pairing = TvPairing::where('claim_token', hash('sha256', $request->claim_token))
        ->where('device_id', $request->device_id)
        ->where('expires_at', '>', now())
        ->firstOrFail();

    $user = $pairing->user;

    UserDevice::updateOrCreate(
        ['device_id' => $pairing->device_id],
        ['user_id' => $user->id, 'device_name' => 'Android TV']
    );

    $token = $user->createToken('tv_apk')->plainTextToken;
    $pairing->delete(); 

    return response()->json([
        'access_token' => $token,
        'user' => $user->load('account')
    ]);
}
}