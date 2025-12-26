<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyRequest;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
   
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'full_name' => $request->full_name,
        ]);

        $otp = rand(100000, 999999);

       
        Cache::put('verification_code_' . $user->id, $otp, 300);

        //this is mock we can't  send sms/email for now
        if ($user->phone) {
            Log::info("SMS OTP for {$user->phone}: {$otp}");
        } else {
            Log::info("Email OTP for {$user->email}: {$otp}");
        }

        return response()->json([
            'message' => 'User registered successfully. Please verify your account.',
            'user_id' => $user->id,
            'debug_otp' => $otp // i'll remove this in production
        ], 201);
    }

   
    public function verify(VerifyRequest $request): JsonResponse
    {
        $cacheKey = 'verification_code_' . $request->user_id;
        $cachedOtp = Cache::get($cacheKey);

        if (!$cachedOtp || $cachedOtp != $request->code) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 400);
        }

        $user = User::find($request->user_id);

        if ($user->phone) {
            $user->phone_verified_at = now();
        }
        if ($user->email) {
            $user->email_verified_at = now();
        }
        
        $user->save();

        Cache::forget($cacheKey);

        $token = $user->createToken('pre_subscription_token',['start:subscription'])->plainTextToken;

        return response()->json([
            'message' => 'Account verified successfully.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where('email', $request->login)
            ->orWhere('phone', $request->login)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->email_verified_at && !$user->phone_verified_at) {
             return response()->json(['message' => 'Account not verified.'], 403);
        }
        $abilities = match($user->role) {
            'admin' => ['*'],
            'subscriber' => ['view:premium'],
            'free' => ['view:free', 'start:subscription'],
        };
        
        
        // Registration
        $token = $user->createToken('auth_token', $abilities)->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }
    
    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}