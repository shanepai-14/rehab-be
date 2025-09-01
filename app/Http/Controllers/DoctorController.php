<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
   
    /**
     * Get doctor dashboard data
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();

            // Mock dashboard data - replace with real data queries
            $dashboardData = [
                'user' => [
                    'name' => $user->full_name,
                    'role' => $user->role
                ],
                'stats' => [
                    'todays_patients' => 12,
                    'total_appointments' => 8,
                    'pending_reviews' => 5,
                    'completed_today' => 3
                ],
                'todays_schedule' => [
                    [
                        'id' => 1,
                        'time' => '09:00',
                        'patient' => 'John Doe',
                        'type' => 'Consultation',
                        'status' => 'scheduled'
                    ],
                    [
                        'id' => 2,
                        'time' => '10:30',
                        'patient' => 'Jane Smith',
                        'type' => 'Follow-up',
                        'status' => 'scheduled'
                    ],
                    [
                        'id' => 3,
                        'time' => '14:00',
                        'patient' => 'Mike Johnson',
                        'type' => 'Check-up',
                        'status' => 'scheduled'
                    ]
                ],
                'recent_patients' => [
                    [
                        'id' => 1,
                        'name' => 'Alice Brown',
                        'last_visit' => now()->subDays(2)->format('M j, Y'),
                        'condition' => 'Hypertension',
                        'status' => 'stable'
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
     * Get doctor's patients
     */
    public function patients(Request $request)
    {
        try {
            // Mock patient data - replace with real queries
            $patients = [
                [
                    'id' => 1,
                    'name' => 'John Doe',
                    'age' => 35,
                    'contact' => '09123456789',
                    'last_visit' => now()->subDays(5)->format('Y-m-d'),
                    'condition' => 'Diabetes',
                    'status' => 'active'
                ],
                [
                    'id' => 2,
                    'name' => 'Jane Smith',
                    'age' => 28,
                    'contact' => '09987654321',
                    'last_visit' => now()->subDays(10)->format('Y-m-d'),
                    'condition' => 'Asthma',
                    'status' => 'active'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $patients
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load patients',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}