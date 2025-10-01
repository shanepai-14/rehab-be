<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Events\SendSmsEvent;
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
    
        try {
        $message = "Your verification code is: {$otpCode}. This code will expire in 5 minutes. Do not share this code with anyone.";
        
        // Ensure the contact number is in E.164 format
        $formattedNumber = $this->formatPhoneNumber($contactNumber);

        event(new SendSmsEvent($formattedNumber, $message));

          return [
                'success' => true,
            ];

            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
        }
        // try {
        //     // Method 1: Disable SSL verification for development (NOT for production)
        //     $httpClient = Http::asForm()
        //         ->withHeaders([
        //             'accept' => 'application/json',
        //             'content-type' => 'application/x-www-form-urlencoded',
        //         ]);

        //     // For development only - disable SSL verification
        //     if (config('app.env') === 'local') {
        //         $httpClient = $httpClient->withOptions([
        //             'verify' => false, // Disable SSL certificate verification
        //             'timeout' => 30,
        //         ]);
        //     }

        //     $response = $httpClient->post($this->apiUrl, [
        //         'api_key' => $this->apiKey,
        //         'api_secret' => $this->apiSecret,
        //         'from' => $this->senderId,
        //         'to' => $formattedNumber,
        //         'text' => $message
        //     ]);

        //     if ($response->successful()) {
        //         $responseData = $response->json();
        //         Log::info("SMS sent successfully to {$formattedNumber}", [
        //             'response' => $responseData
        //         ]);
        //         return [
        //             'success' => true,
        //             'data' => $responseData
        //         ];
        //     } else {
        //         Log::error("Failed to send SMS to {$formattedNumber}", [
        //             'status' => $response->status(),
        //             'response' => $response->body()
        //         ]);
        //         return [
        //             'success' => false,
        //             'error' => $response->body(),
        //             'status' => $response->status()
        //         ];
        //     }
        // } catch (\Exception $e) {
        //     Log::error("SMS sending failed", [
        //         'contact_number' => $formattedNumber,
        //         'error' => $e->getMessage(),
        //         'trace' => $e->getTraceAsString()
        //     ]);
        //     return [
        //         'success' => false,
        //         'error' => $e->getMessage()
        //     ];
        // }
    }

    /**
     * Alternative method using Guzzle directly with SSL options
     */
    public function sendOtpWithGuzzle($contactNumber, $otpCode)
    {
        $message = "Your verification code is: {$otpCode}. This code will expire in 5 minutes. Do not share this code with anyone.";
        $formattedNumber = $this->formatPhoneNumber($contactNumber);

        event(new SendSmsEvent($formattedNumber, $message));
        
        // try {
        //     $client = new \GuzzleHttp\Client([
        //         'timeout' => 30,
        //         'verify' => config('app.env') === 'production', // Only verify SSL in production
        //     ]);

        //     $response = $client->request('POST', $this->apiUrl, [
        //         'form_params' => [
        //             'api_key' => $this->apiKey,
        //             'api_secret' => $this->apiSecret,
        //             'from' => $this->senderId,
        //             'to' => $formattedNumber,
        //             'text' => $message
        //         ],
        //         'headers' => [
        //             'accept' => 'application/json',
        //             'content-type' => 'application/x-www-form-urlencoded',
        //         ],
        //     ]);

        //     $responseData = json_decode($response->getBody(), true);
            
        //     if ($response->getStatusCode() === 200) {
        //         Log::info("SMS sent successfully to {$formattedNumber}", [
        //             'response' => $responseData
        //         ]);
        //         return [
        //             'success' => true,
        //             'data' => $responseData
        //         ];
        //     } else {
        //         Log::error("Failed to send SMS to {$formattedNumber}", [
        //             'status' => $response->getStatusCode(),
        //             'response' => $responseData
        //         ]);
        //         return [
        //             'success' => false,
        //             'error' => $responseData,
        //             'status' => $response->getStatusCode()
        //         ];
        //     }
        // } catch (\Exception $e) {
        //     Log::error("SMS sending failed with Guzzle", [
        //         'contact_number' => $formattedNumber,
        //         'error' => $e->getMessage()
        //     ]);
        //     return [
        //         'success' => false,
        //         'error' => $e->getMessage()
        //     ];
        // }
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
            $httpClient = Http::asForm()
                ->withHeaders([
                    'accept' => 'application/json',
                    'content-type' => 'application/x-www-form-urlencoded',
                ]);

            // For development only - disable SSL verification
            if (config('app.env') === 'local') {
                $httpClient = $httpClient->withOptions([
                    'verify' => false,
                    'timeout' => 30,
                ]);
            }

            $response = $httpClient->post($this->apiUrl, [
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