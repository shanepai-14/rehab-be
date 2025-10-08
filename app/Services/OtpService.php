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

    /**
     * Generate OTP for registration/verification
     */
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

    /**
     * Verify OTP for registration/verification
     */
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

    /**
     * Generate OTP for password reset
     */
    public function generatePasswordResetOtp($contactNumber)
    {
        // Invalidate previous password reset OTPs for this number
        OtpVerification::where('contact_number', $contactNumber)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        // Generate 6-digit OTP
        $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Create new OTP record with longer expiry for password reset
        $otp = OtpVerification::create([
            'contact_number' => $contactNumber,
            'otp_code' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(10), // 10 minutes for password reset
            'is_used' => false,
            'attempts' => 0
        ]);

        // Send SMS with password reset message
        $message = "Your password reset verification code is: {$otpCode}. Valid for 10 minutes. Do not share this code.";
        $smsSent = $this->smsService->sendSms($contactNumber, $message);
        
        return $smsSent;
    }

    /**
     * Verify OTP for password reset
     */
    public function verifyPasswordResetOtp($contactNumber, $otpCode)
    {
        $otp = OtpVerification::where('contact_number', $contactNumber)
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (!$otp) {
            return false;
        }

        // Increment attempts
        $otp->increment('attempts');

        // Check if max attempts exceeded (optional security feature)
        if ($otp->attempts > 5) {
            $otp->update(['is_used' => true]);
            return false;
        }

        // Verify OTP code
        if ($otp->otp_code === $otpCode) {
            $otp->update(['is_used' => true]);
            return true;
        }

        return false;
    }

    /**
     * Check if there's a recent OTP request (rate limiting)
     */
    public function hasRecentOtpRequest($contactNumber, $minutes = 1)
    {
        $recentOtp = OtpVerification::where('contact_number', $contactNumber)
            ->where('created_at', '>', Carbon::now()->subMinutes($minutes))
            ->exists();

        return $recentOtp;
    }

    /**
     * Get remaining time before next OTP can be requested
     */
    public function getRemainingCooldownTime($contactNumber)
    {
        $latestOtp = OtpVerification::where('contact_number', $contactNumber)
            ->latest()
            ->first();

        if (!$latestOtp) {
            return 0;
        }

        $elapsedSeconds = Carbon::now()->diffInSeconds($latestOtp->created_at);
        $cooldownSeconds = 60; // 1 minute cooldown
        $remainingSeconds = max(0, $cooldownSeconds - $elapsedSeconds);

        return $remainingSeconds;
    }

    /**
     * Clean up expired OTPs (can be scheduled)
     */
    public function cleanupExpiredOtps()
    {
        $deleted = OtpVerification::where('expires_at', '<', Carbon::now())
            ->orWhere(function($query) {
                $query->where('is_used', true)
                      ->where('updated_at', '<', Carbon::now()->subDays(7));
            })
            ->delete();

        return $deleted;
    }
}