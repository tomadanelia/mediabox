<?php
namespace App\Http\Controllers;


use App\Http\Requests\Auth\VerifyRequest;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\VerificationService;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;


class SpaAuthController extends Controller
{
    public function __construct(
        private VerificationService $verificationService,
    ) {}

    public function verify(VerifyRequest $request): JsonResponse
    {
        if (! $this->verificationService->validateOtp($request->user_id, $request->code)) {
            return response()->json(['message' => 'Invalid or expired code.'], 400);
        }

        $user = User::findOrFail($request->user_id);
        if ($user->phone) $user->phone_verified_at = now();
        if ($user->email) $user->email_verified_at = now();
        $user->save();

        $this->verificationService->clearOtp($user->id);
        Account::firstOrCreate(['user_id' => $user->id], ['balance' => 0]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['message' => 'Verified and logged in', 'user' => $user]);
    }

    public function verifyLogin(VerifyRequest $request): JsonResponse
    {
        if (! $this->verificationService->validateOtp($request->user_id, $request->code)) {
            return response()->json(['message' => 'Invalid code.'], 400);
        }

        $user = User::findOrFail($request->user_id);
        $this->verificationService->clearOtp($user->id);

        Auth::login($user);
        $request->session()->regenerate();
        
        return response()->json(['message' => 'Login successful', 'user' => $user]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out (Web)']);
    }
}