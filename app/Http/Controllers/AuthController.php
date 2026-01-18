<?php

namespace App\Http\Controllers;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyRequest;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\VerificationService;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\RateLimiter; 
use Illuminate\Support\Str;
class AuthController extends Controller

{
    public function __construct(
        private VerificationService $verificationService,
    ) {}
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'full_name' => $request->full_name,
        ]);
        $otp = $this->verificationService->generateAndSendcode(6,$user);
        
        return response()->json([
            'message' => 'User registered successfully. Please verify your account.',
            'user_id' => $user->id,
            'code' => $otp, // For testing purposes  i am removing  in production
        ], 201);
    }

    public function verify(VerifyRequest $request): JsonResponse
    {
        if (! $this->verificationService->validateOtp($request->user_id, $request->code)) {
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

        $this->verificationService->clearOtp($user->id);

        $token = $user->createToken('pre_subscription_token', ['view:free'])->plainTextToken;
        Account::create([
            'user_id' => $user->id,
            'balance' => 0,
        ]);
        return response()->json([
            'message' => 'Account verified successfully.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    { 
        // throttle login attempts per email/phone counter till 5 in cache and block for 15 minutes
        $throttleKey = 'login_attempt:' . Str::lower($request->login);
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
        $seconds = RateLimiter::availableIn($throttleKey);
        
        throw ValidationException::withMessages([
            'login' => ["Too many login attempts on same email or phone. Please try again in {$seconds} seconds."],
        ])->status(429);
    }
        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    

        $user = User::where($loginType, $request->login)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
         RateLimiter::hit($throttleKey, 900);
        $attemptsLeft = RateLimiter::remaining($throttleKey, 5);

            throw ValidationException::withMessages([
                'login' => ["The provided credentials are incorrect.($attemptsLeft attempts remaining)"],
            ]);
        }

        if (! $user->email_verified_at && ! $user->phone_verified_at) {
            return response()->json(['message' => 'Account not verified.'], 403);
        }
        RateLimiter::clear($throttleKey);


        $abilities = match ($user->role) {
            'admin' => ['*'],
            'subscriber' => ['view:premium'],
            'free' => ['view:free'],
            default => ['view:free'],
        };
        $user->tokens()
       ->where('name', 'pre_subscription_token')
       ->delete();


        $token = $user->createToken('auth_token', $abilities)->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }
    public function resendCode(Request $request): JsonResponse
    {
     $request->validate([
        'login' => 'required|string',
    ]);

    $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    $user = User::where($loginType, $request->login)->first();

    if (! $user) {
        return response()->json(['message' => 'If an account exists, a code has been sent.'], 200);
    }

    if ($user->email_verified_at || $user->phone_verified_at) {
        return response()->json(['message' => 'Account is already verified.'], 400);
    }

    $cooldownKey = 'otp_cooldown_' . $user->id;
    if (Cache::has($cooldownKey)) {
        return response()->json(['message' => 'Please wait before requesting a new code.'], 429);
    }

    $otp = $this->verificationService->generateAndSendcode(6, $user);

    Cache::put($cooldownKey, true, 60);

    return response()->json([
        'message' => 'Verification code sent.',
        'code' => $otp, // For testing purposes; i am removing in production
    ]);

    }


    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
