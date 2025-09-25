<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Validator;
use App\Models\Appointment;
use App\Models\user;

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
            $user = $request->user();
            
            // Check if user is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Build the query with relationships
            $query = Appointment::with(['patient', 'doctor', 'createdBy']);

            // Apply role-based filtering
            switch ($user->role) {
                case User::ROLE_PATIENT:
                    // Patients can only see their own appointments
                    $query->where('patient_id', $user->id);
                    break;

                case User::ROLE_DOCTOR:
                    // Doctors can see appointments assigned to them or patients in their district
                    $query->where(function ($q) use ($user) {
                        $q->where('doctor_id', $user->id)
                        ->orWhereHas('patient', function ($patientQuery) use ($user) {
                            $patientQuery->where('district', $user->district);
                        });
                    });
                    break;

                case User::ROLE_ADMIN:
                    // Admins can see all appointments - no additional filtering needed
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access'
                    ], 403);
            }

            // Apply optional filters from request
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from') && $request->date_from) {
                $query->where('appointment_date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->where('appointment_date', '<=', $request->date_to);
            }

            if ($request->has('priority') && $request->priority) {
                $query->where('priority', $request->priority);
            }

            // Order by appointment date and time
            $appointments = $query->orderBy('appointment_date')
                                ->orderBy('appointment_time')
                                ->get();

            // Transform the data for consistent API response
            $formattedAppointments = $appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'patient_name' => $appointment->patient->first_name ?? 'Unknown Patient',
                    'patient_id' => $appointment->patient_id,
                    'doctor' => 'Dr. '.$appointment->doctor->first_name ?? 'Unassigned',
                    'doctor_id' => $appointment->doctor_id,
                    'specialization' => $appointment->doctor->specialization ?? 'General Medicine',
                    'agenda' => $appointment->agenda,
                    'details' => $appointment->details,
                    'date' => $appointment->appointment_date->format('Y-m-d'),
                    'formatted_date' => $appointment->formatted_date,
                    'time' => $appointment->formatted_time,
                    'raw_time' => $appointment->appointment_time,
                    'location' => $appointment->location,
                    'duration' => $appointment->duration,
                    'priority' => $appointment->priority,
                    'status' => $appointment->status,
                    'notes' => $appointment->notes,
                    'created_at' => $appointment->created_at,
                    'updated_at' => $appointment->updated_at,
                    'created_by' => $appointment->createdBy->name ?? 'System'
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedAppointments,
                'count' => $formattedAppointments->count(),
                'user_role' => $user->role
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