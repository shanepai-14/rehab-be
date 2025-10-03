<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Login user with email or contact number
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'login' => 'required|string',
                'password' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $login = $request->login;
            $password = $request->password;

            // Determine if login is email or contact number
            $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'contact_number';
            
            $user = User::where($field, $login)->first();

            if (!$user || !Hash::check($password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials provided.'
                ], 401);
            }

            if (!$user->is_verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is not verified. Please verify your contact number.',
                    'requires_verification' => true,
                    'contact_number' => $user->contact_number
                ], 403);
            }

            // Create authentication token
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'contact_number' => $user->contact_number,
                    'role' => $user->role,
                    'is_verified' => $user->is_verified
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Register new patient
     */
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'last_name' => 'required|string|max:255',
                'first_name' => 'required|string|max:255',
                'middle_initial' => 'nullable|string|max:1',
                'sex' => 'required|in:male,female,other',
                'birth_date' => 'required|date|before:today',
                'address' => 'required|string|max:500',
                'contact_number' => 'required|string|unique:users,contact_number|regex:/^09[0-9]{9}$/',
                'province' => 'required|string|max:255',
                'municipality' => 'required|string|max:255',
                'barangay' => 'required|string|max:255',
                'district' => 'required|in:1,2,3',
                'email' => 'nullable|email|unique:users,email',
                'patient_type' => 'required|string|max:255',
                'password' => 'required|string|min:8|same:confirm_password',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create user (unverified)
            $user = User::create([
                'last_name' => $request->last_name,
                'first_name' => $request->first_name,
                'middle_initial' => $request->middle_initial,
                'sex' => $request->sex,
                'birth_date' => $request->birth_date,
                'address' => $request->address,
                'contact_number' => $request->contact_number,
                'province' => $request->province,
                'district' => $request->district,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => User::ROLE_PATIENT,
                'is_verified' => false
            ]);

            // Send OTP
            $otpSent = $this->otpService->generateOtp($request->contact_number);

            if (!$otpSent) {
                $user->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification code. Please try again.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Please verify your contact number.',
                'contact_number' => $request->contact_number,
                'user_id' => $user->id
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Send OTP to contact number
     */
    public function sendOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'contact_number' => 'required|string|exists:users,contact_number'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid contact number',
                    'errors' => $validator->errors()
                ], 422);
            }

            $otpSent = $this->otpService->generateOtp($request->contact_number);

            if ($otpSent) {
                return response()->json([
                    'success' => true,
                    'message' => 'Verification code sent successfully.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification code.'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Verify OTP and activate account
     */
    public function verifyOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'contact_number' => 'required|string|exists:users,contact_number',
                'otp_code' => 'required|string|size:6'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $verified = $this->otpService->verifyOtp($request->contact_number, $request->otp_code);

            if (!$verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code.'
                ], 400);
            }

            // Mark user as verified
            $user = User::where('contact_number', $request->contact_number)->first();
            $user->update(['is_verified' => true]);

            // Create authentication token
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Account verified successfully!',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'contact_number' => $user->contact_number,
                    'role' => $user->role,
                    'is_verified' => $user->is_verified
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'OTP verification failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function resendOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'contact_number' => 'required|string|exists:users,contact_number'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid contact number',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if user exists and is not verified
            $user = User::where('contact_number', $request->contact_number)->first();
            
            if ($user->is_verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is already verified.'
                ], 400);
            }

            // Optional: Check for rate limiting (prevent spam)
            $recentOtp = \App\Models\OtpVerification::where('contact_number', $request->contact_number)
                ->where('created_at', '>', now()->subMinute())
                ->first();

            if ($recentOtp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please wait before requesting another code.'
                ], 429);
            }

            // Generate and send new OTP
            $otpSent = $this->otpService->generateOtp($request->contact_number);

            if ($otpSent) {
                return response()->json([
                    'success' => true,
                    'message' => 'Verification code resent successfully.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to resend verification code.'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'contact_number' => $user->contact_number,
                    'role' => $user->role,
                    'is_verified' => $user->is_verified,
                    'created_at' => $user->created_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user information',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}