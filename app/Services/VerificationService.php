<?php
namespace App\Services;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
class VerificationService
{
    /**
     * Generate a random OTP code of specified length and send code via email or SMS
     *
     * @param int $length Length of the OTP code
     * @return string Generated OTP code
     */
    public function generateAndSendcode($user): int
    {
        $otp= rand(100000,999999);
        Cache::put('verification_code_'.$user->id,$otp,300);
        $displayName = $user->username ?? $user->email;
        if ($user->email) {
            Mail::to($user->email)->send(new VerificationCodeMail($otp, $displayName));
        }
        if ($user->phone) {
            $this->sendSms($user->phone, "Your verification code is: {$otp}");
        }
        return $otp;

    }
    private function sendSms(string $phone, string $message): void
{
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) === 9 && str_starts_with($cleanPhone, '5')) {
        $cleanPhone = '995' . $cleanPhone;
    }

    \App\Jobs\SendSmsJob::dispatch($cleanPhone, $message);
}
  /**
     * Validate OTP code from cache
     *
     * @param int $userId User ID to validate against
     * @param string $inputOtp OTP provided by user
     * @return bool True if valid, false otherwise
     */
    public function validateOtp(string $userId, string $inputOtp): bool
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
    public function clearOtp(string $userId): void
    {
        Cache::forget('verification_code_' . $userId);
    }
}
