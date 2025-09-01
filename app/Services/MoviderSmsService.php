<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoviderSmsService
{
    private $apiUrl;
    private $apiKey;
    private $senderId;
    private $apiSecret;

    public function __construct()
    {
        $this->apiUrl = config('services.movider.api_url');
        $this->apiKey = config('services.movider.api_key');
        $this->apiSecret = config('services.movider.api_secret');
        $this->senderId = config('services.movider.sender_id');
    }

    public function sendOtp($contactNumber, $otpCode)
    {
        $message = "Your verification code is: {$otpCode}. This code will expire in 5 minutes. Do not share this code with anyone.";
        
        // Ensure the contact number is in E.164 format
        $formattedNumber = $this->formatPhoneNumber($contactNumber);
        
        try {
            $response = Http::asForm()  // This ensures form-encoded data
                ->withHeaders([
                    'accept' => 'application/json',
                    'content-type' => 'application/x-www-form-urlencoded',
                ])
                ->post($this->apiUrl, [
                    'api_key' => $this->apiKey,
                    'api_secret' => $this->apiSecret,
                    'from' => $this->senderId,
                    'to' => $formattedNumber,
                    'text' => $message
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info("SMS sent successfully to {$formattedNumber}", [
                    'response' => $responseData
                ]);
                return [
                    'success' => true,
                    'data' => $responseData
                ];
            } else {
                Log::error("Failed to send SMS to {$formattedNumber}", [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [
                    'success' => false,
                    'error' => $response->body(),
                    'status' => $response->status()
                ];
            }
        } catch (\Exception $e) {
            Log::error("SMS sending failed", [
                'contact_number' => $formattedNumber,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Format phone number to E.164 format
     * Assumes Philippine numbers if no country code is provided
     */
    private function formatPhoneNumber($phoneNumber)
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If it starts with 63, it's already in the correct format for PH
        if (substr($cleaned, 0, 2) === '63') {
            return '+' . $cleaned;
        }
        
        // If it starts with 0, replace with +63
        if (substr($cleaned, 0, 1) === '0') {
            return '+63' . substr($cleaned, 1);
        }
        
        // If it starts with 9 and is 10 digits, assume it's a PH mobile number
        if (substr($cleaned, 0, 1) === '9' && strlen($cleaned) === 10) {
            return '+63' . $cleaned;
        }
        
        // If it doesn't start with +, add it
        if (substr($phoneNumber, 0, 1) !== '+') {
            return '+' . $cleaned;
        }
        
        return $phoneNumber;
    }

    /**
     * Send SMS to multiple recipients
     */
    public function sendBulkOtp($contactNumbers, $otpCode)
    {
        if (is_array($contactNumbers)) {
            $contactNumbers = array_map([$this, 'formatPhoneNumber'], $contactNumbers);
            $recipients = implode(',', $contactNumbers);
        } else {
            $recipients = $this->formatPhoneNumber($contactNumbers);
        }

        $message = "Your verification code is: {$otpCode}. This code will expire in 5 minutes. Do not share this code with anyone.";
        
        try {
            $response = Http::asForm()
                ->withHeaders([
                    'accept' => 'application/json',
                    'content-type' => 'application/x-www-form-urlencoded',
                ])
                ->post($this->apiUrl, [
                    'api_key' => $this->apiKey,
                    'api_secret' => $this->apiSecret,
                    'from' => $this->senderId,
                    'to' => $recipients,
                    'text' => $message
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info("Bulk SMS sent successfully", [
                    'recipients' => $recipients,
                    'response' => $responseData
                ]);
                return [
                    'success' => true,
                    'data' => $responseData
                ];
            } else {
                Log::error("Failed to send bulk SMS", [
                    'recipients' => $recipients,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [
                    'success' => false,
                    'error' => $response->body(),
                    'status' => $response->status()
                ];
            }
        } catch (\Exception $e) {
            Log::error("Bulk SMS sending failed", [
                'recipients' => $recipients,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}