<?php
namespace App\Services;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
class VerificationService
{
    /**
     * Generate a random OTP code of specified length and send code via email or SMS
     *
     * @param int $length Length of the OTP code
     * @return string Generated OTP code
     */
    public function generateAndSendcode(int $length = 6,$user): int
    {
        $otp= rand(100000,999999);
        Cache::put('verification_code_'.$user->id,$otp,300);
        if ($user->email) {
            Mail::to($user->email)->send(new VerificationCodeMail($otp, $user->username));
        } elseif ($user->phone) {
            // SMS logic would go here when implemented
            Log::info("SMS OTP for {$user->phone}: {$otp}");
        }
        return $otp;

    }
  /**
     * Validate OTP code from cache
     *
     * @param int $userId User ID to validate against
     * @param string $inputOtp OTP provided by user
     * @return bool True if valid, false otherwise
     */
    public function validateOtp(int $userId, string $inputOtp): bool
    {
        $cacheKey = 'verification_code_' . $userId;
        $cachedOtp = Cache::get($cacheKey);
        
        if (!$cachedOtp) {
            return false;
        }
        
        return hash_equals((string)$cachedOtp, $inputOtp);
    }
    
    /**
     * Clear OTP from cache after verification
     *
     * @param int $userId User ID
     * @return void
     */
    public function clearOtp(int $userId): void
    {
        Cache::forget('verification_code_' . $userId);
    }
}
