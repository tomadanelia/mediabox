<?php
namespace App\Http\Controllers;

use App\Models\TvPairing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TvPairingController extends Controller
{
    public function initialize(Request $request)
    {
        $request->validate(['device_id' => 'required|string']);

        $code = strtoupper(Str::random(6));
        
        TvPairing::create([
            'pairing_code' => $code,
            'device_id' => $request->device_id,
            'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json([
            'pairing_code' => $code,
            'qr_url' => "https://tv-api.telecomm1.com/tv-register?code={$code}"
        ]);
    }

    public function checkStatus(Request $request)
    {
        $request->validate([
            'pairing_code' => 'required|string',
            'device_id' => 'required|string'
        ]);

        $pairing = TvPairing::where('pairing_code', $request->pairing_code)
            ->where('device_id', $request->device_id)
            ->first();

        if (!$pairing || $pairing->expires_at->isPast()) {
            return response()->json(['status' => 'expired'], 410);
        }

        if ($pairing->user_id) {
            $user = User::find($pairing->user_id);
            $user->tokens()->where('name', 'tv_apk')->delete(); 
            $token = $user->createToken('tv_apk')->plainTextToken;
            
            $pairing->delete(); 

            return response()->json([
                'status' => 'paired',
                'access_token' => $token,
                'user' => $user
            ]);
        }

        return response()->json(['status' => 'pending']);
    }

    //this one is called by my web spa
    public function pair(Request $request)
    {
        $request->validate(['pairing_code' => 'required|string']);

        $pairing = TvPairing::where('pairing_code', $request->pairing_code)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $pairing->update(['user_id' => $request->user()->id]);

        return response()->json(['message' => 'TV successfully paired!']);
    }
}