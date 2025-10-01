<?php

namespace App\Services;

use App\Models\OtpVerification;
use Carbon\Carbon;

class OtpService
{
    private $smsService;

    public function __construct(MoviderSmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function generateOtp($contactNumber)
    {
        // Invalidate previous OTPs for this number
        OtpVerification::where('contact_number', $contactNumber)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        // Generate 6-digit OTP
        $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);


        
      // Create new OTP record
        $otp = OtpVerification::create([
            'contact_number' => $contactNumber,
            'otp_code' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Send SMS
        $smsSent = $this->smsService->sendOtp($contactNumber, $otpCode);
        
        return $smsSent;
    }

    public function verifyOtp($contactNumber, $otpCode)
    {
        $otp = OtpVerification::where('contact_number', $contactNumber)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (!$otp) {
            return false;
        }

        // Increment attempts
        $otp->increment('attempts');

        if ($otp->isValid($otpCode)) {
            $otp->update(['is_used' => true]);
            return true;
        }

        return false;
    }
}
