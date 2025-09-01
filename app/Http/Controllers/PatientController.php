<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PatientController extends Controller
{
 
    /**
     * Get patient dashboard data
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
                    'next_appointment' => 'Tomorrow 2:00 PM',
                    'health_score' => 85,
                    'total_appointments' => 12,
                    'completed_treatments' => 8
                ],
                'recent_activity' => [
                    [
                        'type' => 'appointment',
                        'title' => 'Appointment completed',
                        'description' => '2 days ago with Dr. Smith',
                        'date' => now()->subDays(2)->format('M j, Y'),
                        'status' => 'completed'
                    ],
                    [
                        'type' => 'lab_result',
                        'title' => 'Lab results available',
                        'description' => '1 week ago',
                        'date' => now()->subWeek()->format('M j, Y'),
                        'status' => 'available'
                    ]
                ],
                'upcoming_appointments' => [
                    [
                        'id' => 1,
                        'doctor' => 'Dr. Smith',
                        'date' => now()->addDay()->format('Y-m-d'),
                        'time' => '14:00',
                        'type' => 'Check-up'
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
     * Get patient appointments
     */
    public function appointments(Request $request)
    {
        try {
            // Mock appointment data - replace with real queries
            $appointments = [
                [
                    'id' => 1,
                    'doctor' => 'Dr. Smith',
                    'specialization' => 'General Medicine',
                    'date' => now()->addDay()->format('Y-m-d'),
                    'time' => '14:00',
                    'type' => 'Check-up',
                    'status' => 'confirmed'
                ],
                [
                    'id' => 2,
                    'doctor' => 'Dr. Johnson',
                    'specialization' => 'Cardiology',
                    'date' => now()->addDays(3)->format('Y-m-d'),
                    'time' => '10:30',
                    'type' => 'Consultation',
                    'status' => 'pending'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $appointments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load appointments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}