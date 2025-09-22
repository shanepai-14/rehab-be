<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Create a new appointment
     * Only Doctors and Admins can create appointments
     */
    public function create(Request $request)
    {
        try {
            $user = $request->user();

            // Check if user has permission to create appointments
            if (!in_array($user->role, [User::ROLE_DOCTOR, User::ROLE_ADMIN])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only doctors and admins can create appointments.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'patient_id' => 'required|exists:users,id',
                'agenda' => 'required|string|max:255',
                'details' => 'required|string',
                'appointment_date' => 'required|date|after:now',
                'appointment_time' => 'required|date_format:H:i',
                'location' => 'nullable|string|max:255',
                'duration' => 'nullable|integer|min:15|max:240', // in minutes
                'priority' => 'nullable|in:low,normal,high,urgent'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get patient details
            $patient = User::where('id', $request->patient_id)
                           ->where('role', User::ROLE_PATIENT)
                           ->first();

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            // For doctors, check if patient is in their assigned district
            if ($user->role === User::ROLE_DOCTOR) {
                if (!$this->canDoctorAccessPatient($user, $patient)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only create appointments for patients in your assigned district.'
                    ], 403);
                }
            }

            // Create appointment
            DB::beginTransaction();

            $appointment = Appointment::create([
                'patient_id' => $request->patient_id,
                'doctor_id' => $user->role === User::ROLE_DOCTOR ? $user->id : $request->doctor_id,
                'created_by' => $user->id,
                'agenda' => $request->agenda,
                'details' => $request->details,
                'appointment_date' => $request->appointment_date,
                'appointment_time' => $request->appointment_time,
                'location' => $request->location,
                'duration' => $request->duration ?? 30,
                'priority' => $request->priority ?? 'normal',
                'status' => 'scheduled'
            ]);

            DB::commit();

            // Send notifications
            $this->sendAppointmentNotifications($appointment, 'created');

            return response()->json([
                'success' => true,
                'message' => 'Appointment created successfully',
                'data' => [
                    'appointment' => $appointment->load(['patient', 'doctor', 'createdBy'])
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create appointment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get appointments based on user role
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = Appointment::with(['patient', 'doctor', 'createdBy']);

            // Apply role-based filtering
            switch ($user->role) {
                case User::ROLE_PATIENT:
                    $query->where('patient_id', $user->id);
                    break;

                case User::ROLE_DOCTOR:
                    // Doctors can see appointments for patients in their district
                    $query->where(function ($q) use ($user) {
                        $q->where('doctor_id', $user->id)
                          ->orWhereHas('patient', function ($patientQuery) use ($user) {
                              $patientQuery->where('district_id', $user->district_id);
                          });
                    });
                    break;

                case User::ROLE_ADMIN:
                    // Admins can see all appointments
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access'
                    ], 403);
            }

            // Apply filters
            if ($request->has('date_from')) {
                $query->where('appointment_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('appointment_date', '<=', $request->date_to);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }

            $appointments = $query->orderBy('appointment_date')
                                 ->orderBy('appointment_time')
                                 ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $appointments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve appointments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update appointment status
     */
    public function updateStatus(Request $request, $appointmentId)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:scheduled,confirmed,in_progress,completed,cancelled,no_show',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $appointment = Appointment::findOrFail($appointmentId);

            // Check permissions
            if (!$this->canUserModifyAppointment($user, $appointment)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to modify this appointment'
                ], 403);
            }

            $oldStatus = $appointment->status;
            $appointment->update([
                'status' => $request->status,
                'notes' => $request->notes,
                'updated_by' => $user->id
            ]);

            // Send notifications if status changed significantly
            if ($this->shouldNotifyStatusChange($oldStatus, $request->status)) {
                $this->sendAppointmentNotifications($appointment, 'status_changed');
            }

            return response()->json([
                'success' => true,
                'message' => 'Appointment status updated successfully',
                'data' => $appointment->load(['patient', 'doctor'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update appointment status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get patients accessible to the current user (for doctors)
     */
    public function getAccessiblePatients(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role !== User::ROLE_DOCTOR) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only doctors can access this endpoint'
                ], 403);
            }

            // Get patients in doctor's assigned district
            $patients = User::where('role', User::ROLE_PATIENT)
                           ->where('district_id', $user->district_id)
                           ->select(['id', 'first_name', 'last_name', 'middle_initial', 'contact_number', 'email'])
                           ->get();

            return response()->json([
                'success' => true,
                'data' => $patients
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patients',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check if doctor can access a specific patient
     */
    protected function canDoctorAccessPatient($doctor, $patient)
    {
        return $doctor->district === $patient->district;
    }

    /**
     * Check if user can modify an appointment
     */
    protected function canUserModifyAppointment($user, $appointment)
    {
        switch ($user->role) {
            case User::ROLE_ADMIN:
                return true;

            case User::ROLE_DOCTOR:
                return $appointment->doctor_id === $user->id || 
                       $this->canDoctorAccessPatient($user, $appointment->patient);

            case User::ROLE_PATIENT:
                return $appointment->patient_id === $user->id;

            default:
                return false;
        }
    }

    /**
     * Send notifications for appointment events using Laravel Events
     */
    protected function sendAppointmentNotifications($appointment, $event)
    {
        try {
            $appointment->load(['patient', 'doctor']);

            // Send SMS via Movider
            $this->notificationService->sendSmsNotification($appointment, $event);

            // Send real-time notification via Laravel Events/Broadcasting
            $this->notificationService->sendRealtimeNotification($appointment, $event);

        } catch (\Exception $e) {
            // Log notification errors but don't fail the main operation
            logger()->error('Failed to send appointment notification', [
                'appointment_id' => $appointment->id,
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if status change requires notification
     */
    protected function shouldNotifyStatusChange($oldStatus, $newStatus)
    {
        $notifiableChanges = [
            'scheduled' => ['confirmed', 'cancelled'],
            'confirmed' => ['in_progress', 'cancelled', 'no_show'],
            'in_progress' => ['completed'],
        ];

        return isset($notifiableChanges[$oldStatus]) && 
               in_array($newStatus, $notifiableChanges[$oldStatus]);
    }


    public function show(Request $request, Appointment $appointment)
{
    try {
        $appointment->load(['patient', 'doctor', 'createdBy', 'updatedBy']);

        return response()->json([
            'success' => true,
            'data' => $appointment
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve appointment',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

/**
 * Update appointment details
 */
public function update(Request $request, Appointment $appointment)
{
    try {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'agenda' => 'sometimes|required|string|max:255',
            'details' => 'sometimes|required|string',
            'appointment_date' => 'sometimes|required|date|after:now',
            'appointment_time' => 'sometimes|required|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'duration' => 'nullable|integer|min:15|max:240',
            'priority' => 'nullable|in:low,normal,high,urgent'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $appointment->toArray();
        
        $appointment->update(array_merge(
            $request->only(['agenda', 'details', 'appointment_date', 'appointment_time', 'location', 'duration', 'priority']),
            ['updated_by' => $user->id]
        ));

        // Send notification if significant changes were made
        $significantFields = ['appointment_date', 'appointment_time', 'agenda'];
        $hasSignificantChanges = collect($significantFields)->some(function ($field) use ($request, $oldData) {
            return $request->has($field) && $request->get($field) != $oldData[$field];
        });

        if ($hasSignificantChanges) {
            $this->notificationService->sendAppointmentNotifications($appointment, 'updated');
        }

        return response()->json([
            'success' => true,
            'message' => 'Appointment updated successfully',
            'data' => $appointment->load(['patient', 'doctor', 'updatedBy'])
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update appointment',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

/**
 * Delete appointment (soft delete)
 */
public function destroy(Request $request, Appointment $appointment)
{
    try {
        $user = $request->user();
        
        // Store appointment data before deletion for notification
        $appointmentData = $appointment->load(['patient', 'doctor']);
        
        $appointment->update(['updated_by' => $user->id]);
        $appointment->delete();

        // Send cancellation notification
        $this->notificationService->sendAppointmentNotifications($appointmentData, 'cancelled');

        return response()->json([
            'success' => true,
            'message' => 'Appointment deleted successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete appointment',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

/**
 * Get appointment statistics for dashboards
 */
public function getStatistics(Request $request)
{
    try {
        $user = $request->user();
        $query = Appointment::query();

        // Apply role-based filtering
        switch ($user->role) {
            case User::ROLE_DOCTOR:
                $query->where(function ($q) use ($user) {
                    $q->where('doctor_id', $user->id)
                      ->orWhereHas('patient', function ($patientQuery) use ($user) {
                          $patientQuery->where('district', $user->district);
                      });
                });
                break;
            case User::ROLE_PATIENT:
                $query->where('patient_id', $user->id);
                break;
            case User::ROLE_ADMIN:
                // No filtering for admin
                break;
        }

        $stats = [
            'total' => $query->count(),
            'today' => (clone $query)->whereDate('appointment_date', now())->count(),
            'this_week' => (clone $query)->whereBetween('appointment_date', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'this_month' => (clone $query)->whereMonth('appointment_date', now()->month)->count(),
            'by_status' => (clone $query)->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->pluck('count', 'status')
                ->toArray(),
            'by_priority' => (clone $query)->groupBy('priority')
                ->selectRaw('priority, count(*) as count')
                ->pluck('count', 'priority')
                ->toArray(),
            'upcoming' => (clone $query)->where('appointment_date', '>=', now())
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve statistics',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}
}
