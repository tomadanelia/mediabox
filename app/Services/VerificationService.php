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
     * Generate a random OTP code of specified length
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
     * Validate the provided OTP code against the expected code
     *
     * @param string $inputOtp The OTP code provided by the user
     * @param string $expectedOtp The expected OTP code to validate against
     * @return bool True if the OTP codes match, false otherwise
     */
    public function validateOtp(string $inputOtp, string $expectedOtp): bool
    {
        return hash_equals($expectedOtp, $inputOtp);
    }
}   