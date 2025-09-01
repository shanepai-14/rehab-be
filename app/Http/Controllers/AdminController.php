<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{

    /**
     * Get admin dashboard data
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();

            // Get real system statistics
            $totalPatients = User::where('role', User::ROLE_PATIENT)->count();
            $totalDoctors = User::where('role', User::ROLE_DOCTOR)->count();
            $verifiedUsers = User::where('is_verified', true)->count();
            $pendingVerifications = User::where('is_verified', false)->count();

            $dashboardData = [
                'user' => [
                    'name' => $user->full_name,
                    'role' => $user->role
                ],
                'stats' => [
                    'total_patients' => $totalPatients,
                    'active_doctors' => $totalDoctors,
                    'total_appointments' => 342, // Mock data - replace with real query
                    'system_health' => 98,
                    'verified_users' => $verifiedUsers,
                    'pending_verifications' => $pendingVerifications
                ],
                'recent_activities' => [
                    [
                        'type' => 'user_registration',
                        'description' => 'New patient registered',
                        'timestamp' => now()->subHour()->format('M j, Y H:i')
                    ],
                    [
                        'type' => 'doctor_created',
                        'description' => 'New doctor account created',
                        'timestamp' => now()->subHours(3)->format('M j, Y H:i')
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $dashboardData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create new doctor account
     */
    public function createDoctor(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'last_name' => 'required|string|max:255',
                'first_name' => 'required|string|max:255',
                'middle_initial' => 'nullable|string|max:1',
                'sex' => 'required|in:male,female,other',
                'birth_date' => 'required|date',
                'address' => 'required|string|max:500',
                'contact_number' => 'required|string|unique:users,contact_number|regex:/^09[0-9]{9}$/',
                'province' => 'required|string|max:255',
                'district' => 'required|in:1,2,3',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
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

            $doctor = User::create([
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
                'role' => User::ROLE_DOCTOR,
                'is_verified' => true,
                'created_by' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Doctor account created successfully!',
                'doctor' => [
                    'id' => $doctor->id,
                    'name' => $doctor->full_name,
                    'email' => $doctor->email,
                    'contact_number' => $doctor->contact_number,
                    'role' => $doctor->role
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create doctor account',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get all users with filtering
     */
    public function users(Request $request)
    {
        try {
            $query = User::query();

            // Filter by role if provided
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            // Filter by verification status if provided
            if ($request->has('verified')) {
                $query->where('is_verified', $request->boolean('verified'));
            }

            // Search by name or email
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->select([
                'id', 'first_name', 'last_name', 'middle_initial', 'email', 
                'contact_number', 'role', 'is_verified', 'created_at'
            ])->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load users',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get system statistics
     */
    public function statistics(Request $request)
    {
        try {
            $stats = [
                'users' => [
                    'total' => User::count(),
                    'patients' => User::where('role', User::ROLE_PATIENT)->count(),
                    'doctors' => User::where('role', User::ROLE_DOCTOR)->count(),
                    'admins' => User::where('role', User::ROLE_ADMIN)->count(),
                    'verified' => User::where('is_verified', true)->count(),
                    'pending_verification' => User::where('is_verified', false)->count()
                ],
                'registrations_this_month' => User::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'recent_registrations' => User::latest()
                    ->limit(5)
                    ->select(['id', 'first_name', 'last_name', 'role', 'created_at'])
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}