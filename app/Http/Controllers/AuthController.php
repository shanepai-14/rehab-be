<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OtpVerification;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
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
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'middle_initial' => $user->middle_initial,
                    'province' => $user->province,
                    'municipality' => $user->municipality,
                    'barangay' => $user->barangay,
                    'district' => $user->district,
                    'address' => $user->address,
                    'patient_type' => $user->patient_type,
                    'birth_date' => $user->birth_date,
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                    'is_verified' => $user->is_verified,
                    'specialization' => $user->specialization,
                    'license_number' => $user->license_number,
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
     * Update patient profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'middle_initial' => 'nullable|string|max:1',
                'sex' => 'required|in:male,female,other',
                'birth_date' => 'required|date|before:today',
                'address' => 'required|string',
                'province' => 'required|string|max:255',
                'patient_type' => 'nullable|string|max:255',
                'municipality' => 'nullable|string|max:255',
                'barangay' => 'nullable|string|max:255',
                'specialization' => 'nullable|string|max:255',
                'license_number' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update only allowed fields
            $user->update($request->only([
                'first_name',
                'last_name',
                'middle_initial',
                'sex',
                'birth_date',
                'address',
                'province',
                'municipality',
                'barangay',
                'patient_type',
                'specialization',
                'license_number'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
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

    /**
     * Resend OTP
     */
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

            // Check for rate limiting using service
            if ($this->otpService->hasRecentOtpRequest($request->contact_number)) {
                $remainingSeconds = $this->otpService->getRemainingCooldownTime($request->contact_number);
                
                return response()->json([
                    'success' => false,
                    'message' => "Please wait {$remainingSeconds} seconds before requesting another code."
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
     * FORGOT PASSWORD - Send OTP for password reset
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'contact' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid input',
                    'errors' => $validator->errors()
                ], 422);
            }

            $contact = $request->contact;
            
            // Find user by email or phone
            $user = User::where('email', $contact)
                        ->orWhere('contact_number', $contact)
                        ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found with this email or phone number'
                ], 404);
            }

            // Check for rate limiting
            if ($this->otpService->hasRecentOtpRequest($user->contact_number)) {
                $remainingSeconds = $this->otpService->getRemainingCooldownTime($user->contact_number);
                
                return response()->json([
                    'success' => false,
                    'message' => "Please wait {$remainingSeconds} seconds before requesting another code."
                ], 429);
            }

            // Generate and send password reset OTP using service
            $otpSent = $this->otpService->generatePasswordResetOtp($user->contact_number);

            if (!$otpSent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification code. Please try again.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Verification code sent successfully',
                'contact_number' => $user->contact_number
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code. Please try again.',
                'error' =>  $e->getMessage() 
            ], 500);
        }
    }

    /**
     * VERIFY RESET OTP - Verify OTP for password reset
     */
    public function verifyResetOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'contact_number' => 'required|string',
                'otp' => 'required|string|size:6'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid input',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify OTP using service
            $verified = $this->otpService->verifyPasswordResetOtp($request->contact_number, $request->otp);

            if (!$verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code'
                ], 400);
            }

            // Generate reset token
            $resetToken = Str::random(64);

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'reset_token' => $resetToken
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed. Please try again.',
                'error' =>  $e->getMessage() 
            ], 500);
        }
    }

    /**
     * RESET PASSWORD - Reset user password
     */
    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reset_token' => 'required|string',
                'contact_number' => 'required|string',
                'new_password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/[a-z]/',      // at least one lowercase
                    'regex:/[A-Z]/',      // at least one uppercase
                    'regex:/[0-9]/',      // at least one number
                ],
                'confirm_password' => 'required|string|same:new_password'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find user
            $user = User::where('contact_number', $request->contact_number)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Verify that OTP was used recently (within last 30 minutes)
            $recentOtp = OtpVerification::where('contact_number', $request->contact_number)
                ->where('is_used', true)
                ->where('updated_at', '>', Carbon::now()->subMinutes(30))
                ->first();

            if (!$recentOtp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reset session expired. Please request a new verification code.'
                ], 400);
            }

            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            // Delete the OTP record
            $recentOtp->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again.',
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