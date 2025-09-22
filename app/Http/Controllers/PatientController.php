<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Validator;
use App\Models\Appointment;
class PatientController extends Controller
{

     protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
 
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

    public function cancelAppointment(Request $request, Appointment $appointment)
{
    try {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $appointment->update([
            'status' => 'cancelled',
            'notes' => $request->reason ? "Cancelled by patient: " . $request->reason : "Cancelled by patient",
            'updated_by' => $request->user()->id
        ]);

        $this->notificationService->sendAppointmentNotifications($appointment, 'cancelled');

        return response()->json([
            'success' => true,
            'message' => 'Appointment cancelled successfully',
            'data' => $appointment->load(['patient', 'doctor'])
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to cancel appointment',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}
}