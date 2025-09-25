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
use Carbon\Carbon;

class AppointmentController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

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
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'duration' => 'nullable|integer|min:15|max:240', // in minutes
            'priority' => 'nullable|in:low,normal,high,urgent',
            'doctor_id' => 'nullable|exists:users,id' // For admin creating appointments
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

        // Determine the doctor for this appointment
        $doctorId = $user->role === User::ROLE_DOCTOR ? $user->id : $request->doctor_id;
        
        if (!$doctorId) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor ID is required for admin-created appointments'
            ], 422);
        }

        // Validate doctor exists and has correct role
        $doctor = User::where('id', $doctorId)
                     ->where('role', User::ROLE_DOCTOR)
                     ->first();

        if (!$doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found'
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

        // Calculate appointment duration and end time
        $duration = $request->duration ?? 30; // Default 30 minutes
        
        // Normalize time format - remove seconds if present to match database format
        $appointmentTime = $request->appointment_time;
        if (strlen($appointmentTime) > 5) {
            $appointmentTime = substr($appointmentTime, 0, 5); // Convert "09:00:00" to "09:00"
        }
        
        // Properly parse the date and time
        try {
            $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i', $request->appointment_date . ' ' . $appointmentTime);
            $appointmentEndTime = $appointmentDateTime->copy()->addMinutes($duration);
            
            \Log::info('Creating appointment with parsed datetime', [
                'original_time' => $request->appointment_time,
                'normalized_time' => $appointmentTime,
                'parsed_datetime' => $appointmentDateTime->toDateTimeString(),
                'end_datetime' => $appointmentEndTime->toDateTimeString(),
                'duration' => $duration
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to parse appointment datetime', [
                'date' => $request->appointment_date,
                'original_time' => $request->appointment_time,
                'normalized_time' => $appointmentTime,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid date or time format',
                'error' => 'Please ensure date is in YYYY-MM-DD format and time is in HH:MM or HH:MM:SS format'
            ], 422);
        }

        // Check for schedule overlaps
        $overlapCheck = $this->checkScheduleOverlap(
            $doctorId,
            $request->patient_id,
            $appointmentDateTime,
            $appointmentEndTime,
            null // No appointment ID for new appointments
        );

        if (!$overlapCheck['success']) {
            return response()->json($overlapCheck, 409); // 409 Conflict
        }

        // Validate business hours (8 AM to 6 PM)
        $startHour = $appointmentDateTime->hour;
        $endHour = $appointmentEndTime->hour;
        $endMinute = $appointmentEndTime->minute;

        if ($startHour < 8 || $endHour > 18 || ($endHour === 18 && $endMinute > 0)) {
            return response()->json([
                'success' => false,
                'message' => 'Appointments must be scheduled between 8:00 AM and 6:00 PM'
            ], 422);
        }

        // Check if appointment is on a weekend (optional business rule)
        if ($appointmentDateTime->isWeekend()) {
            return response()->json([
                'success' => false,
                'message' => 'Appointments cannot be scheduled on weekends'
            ], 422);
        }

        // Create appointment
        DB::beginTransaction();

        $appointment = Appointment::create([
            'patient_id' => $request->patient_id,
            'doctor_id' => $doctorId,
            'created_by' => $user->id,
            'agenda' => $request->agenda,
            'details' => $request->details,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'location' => $request->location,
            'duration' => $duration,
            'priority' => $request->priority ?? 'normal',
            'status' => 'scheduled'
        ]);

        DB::commit();

        // Send notifications (uncomment when ready)
        // $this->sendAppointmentNotifications($appointment, 'created');

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
 * Check for schedule overlaps for both doctor and patient
 */
private function checkScheduleOverlap($doctorId, $patientId, $startDateTime, $endDateTime, $excludeAppointmentId = null)
{
    \Log::info('Starting overlap check', [
        'doctor_id' => $doctorId,
        'patient_id' => $patientId,
        'new_start' => $startDateTime->toDateTimeString(),
        'new_end' => $endDateTime->toDateTimeString(),
        'exclude_appointment_id' => $excludeAppointmentId,
        'date_for_query' => $startDateTime->format('Y-m-d')
    ]);

    // Check doctor schedule conflicts
    $doctorAppointments = Appointment::where('doctor_id', $doctorId)
        ->where('appointment_date', $startDateTime->format('Y-m-d'))
        ->whereNotIn('status', ['cancelled', 'no_show'])
        ->when($excludeAppointmentId, function ($query) use ($excludeAppointmentId) {
            return $query->where('id', '!=', $excludeAppointmentId);
        })
        ->with(['patient'])
        ->get();

    \Log::info('Found doctor appointments for conflict check', [
        'count' => $doctorAppointments->count(),
        'appointments' => $doctorAppointments->map(function($apt) {
            return [
                'id' => $apt->id,
                'date' => $apt->appointment_date,
                'time' => $apt->appointment_time, // Should now be clean "09:00" format
                'duration' => $apt->duration,
                'status' => $apt->status,
                'patient_name' => $apt->patient->first_name . ' ' . $apt->patient->last_name
            ];
        })->toArray()
    ]);

    $doctorConflicts = $doctorAppointments->filter(function ($appointment) use ($startDateTime, $endDateTime) {
        try {
            // With the model accessor, appointment_time should now be clean "09:00" format
            $existingStart = Carbon::createFromFormat('Y-m-d H:i', $appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time);
            $existingEnd = $existingStart->copy()->addMinutes($appointment->duration ?? 30);
            
            \Log::debug('Checking doctor appointment overlap', [
                'appointment_id' => $appointment->id,
                'appointment_time' => $appointment->appointment_time,
                'existing_start' => $existingStart->toDateTimeString(),
                'existing_end' => $existingEnd->toDateTimeString(),
                'new_start' => $startDateTime->toDateTimeString(),
                'new_end' => $endDateTime->toDateTimeString(),
                'overlaps' => $startDateTime->lt($existingEnd) && $endDateTime->gt($existingStart)
            ]);
            
            // Check for overlap: new appointment overlaps if it starts before existing ends 
            // AND ends after existing starts
            return $startDateTime->lt($existingEnd) && $endDateTime->gt($existingStart);
            
        } catch (\Exception $e) {
            \Log::error('Failed to parse existing appointment datetime', [
                'appointment_id' => $appointment->id,
                'date' => $appointment->appointment_date,
                'time' => $appointment->appointment_time,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    });

    if ($doctorConflicts->isNotEmpty()) {
        $conflictTime = $doctorConflicts->first();
        
        \Log::warning('Doctor schedule conflict detected', [
            'conflict_appointment_id' => $conflictTime->id,
            'conflict_time' => $conflictTime->appointment_time,
            'conflict_duration' => $conflictTime->duration,
            'conflict_patient' => $conflictTime->patient->first_name . ' ' . $conflictTime->patient->last_name
        ]);
        
        try {
            $conflictStart = Carbon::createFromFormat('Y-m-d H:i', $conflictTime->appointment_date . ' ' . $conflictTime->appointment_time);
            
            return [
                'success' => false,
                'message' => 'Doctor schedule conflict detected',
                'details' => [
                    'conflict_type' => 'doctor',
                    'existing_appointment' => [
                        'id' => $conflictTime->id,
                        'patient' => $conflictTime->patient->first_name . ' ' . $conflictTime->patient->last_name,
                        'time' => $conflictStart->format('M j, Y g:i A'),
                        'duration' => $conflictTime->duration . ' minutes',
                        'agenda' => $conflictTime->agenda ?? 'Unknown'
                    ]
                ]
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to format conflict appointment time', [
                'appointment_id' => $conflictTime->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Doctor schedule conflict detected',
                'details' => [
                    'conflict_type' => 'doctor',
                    'existing_appointment' => [
                        'id' => $conflictTime->id,
                        'patient' => $conflictTime->patient->first_name . ' ' . $conflictTime->patient->last_name,
                        'raw_time' => $conflictTime->appointment_time,
                        'duration' => $conflictTime->duration . ' minutes'
                    ]
                ]
            ];
        }
    }

    // Check patient schedule conflicts
    $patientAppointments = Appointment::where('patient_id', $patientId)
        ->where('appointment_date', $startDateTime->format('Y-m-d'))
        ->whereNotIn('status', ['cancelled', 'no_show'])
        ->when($excludeAppointmentId, function ($query) use ($excludeAppointmentId) {
            return $query->where('id', '!=', $excludeAppointmentId);
        })
        ->with(['doctor'])
        ->get();

    \Log::info('Found patient appointments for conflict check', [
        'count' => $patientAppointments->count(),
        'appointments' => $patientAppointments->map(function($apt) {
            return [
                'id' => $apt->id,
                'date' => $apt->appointment_date,
                'time' => $apt->appointment_time, // Should now be clean "09:00" format
                'duration' => $apt->duration,
                'status' => $apt->status,
                'doctor_name' => $apt->doctor->first_name . ' ' . $apt->doctor->last_name
            ];
        })->toArray()
    ]);

    $patientConflicts = $patientAppointments->filter(function ($appointment) use ($startDateTime, $endDateTime) {
        try {
            // With the model accessor, appointment_time should now be clean "09:00" format
            $existingStart = Carbon::createFromFormat('Y-m-d H:i', $appointment->appointment_date . ' ' . $appointment->appointment_time);
            $existingEnd = $existingStart->copy()->addMinutes($appointment->duration ?? 30);
            
            \Log::debug('Checking patient appointment overlap', [
                'appointment_id' => $appointment->id,
                'appointment_time' => $appointment->appointment_time,
                'existing_start' => $existingStart->toDateTimeString(),
                'existing_end' => $existingEnd->toDateTimeString(),
                'new_start' => $startDateTime->toDateTimeString(),
                'new_end' => $endDateTime->toDateTimeString(),
                'overlaps' => $startDateTime->lt($existingEnd) && $endDateTime->gt($existingStart)
            ]);
            
            return $startDateTime->lt($existingEnd) && $endDateTime->gt($existingStart);
            
        } catch (\Exception $e) {
            \Log::error('Failed to parse patient appointment datetime', [
                'appointment_id' => $appointment->id,
                'date' => $appointment->appointment_date,
                'time' => $appointment->appointment_time,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    });

    if ($patientConflicts->isNotEmpty()) {
        $conflictTime = $patientConflicts->first();
        
        \Log::warning('Patient schedule conflict detected', [
            'conflict_appointment_id' => $conflictTime->id,
            'conflict_time' => $conflictTime->appointment_time,
            'conflict_duration' => $conflictTime->duration,
            'conflict_doctor' => $conflictTime->doctor->first_name . ' ' . $conflictTime->doctor->last_name
        ]);
        
        try {
            $conflictStart = Carbon::createFromFormat('Y-m-d H:i', $conflictTime->appointment_date . ' ' . $conflictTime->appointment_time);
            
            return [
                'success' => false,
                'message' => 'Patient schedule conflict detected',
                'details' => [
                    'conflict_type' => 'patient',
                    'existing_appointment' => [
                        'id' => $conflictTime->id,
                        'doctor' => $conflictTime->doctor->first_name . ' ' . $conflictTime->doctor->last_name,
                        'time' => $conflictStart->format('M j, Y g:i A'),
                        'duration' => $conflictTime->duration . ' minutes',
                        'agenda' => $conflictTime->agenda ?? 'Unknown'
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Patient schedule conflict detected',
                'details' => [
                    'conflict_type' => 'patient',
                    'existing_appointment' => [
                        'id' => $conflictTime->id,
                        'doctor' => $conflictTime->doctor->first_name . ' ' . $conflictTime->doctor->last_name,
                        'raw_time' => $conflictTime->appointment_time,
                        'duration' => $conflictTime->duration . ' minutes'
                    ]
                ]
            ];
        }
    }

    \Log::info('No schedule conflicts found');
    return ['success' => true];
}

/**
 * Get available time slots for a doctor on a specific date
 */
public function getAvailableSlots(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'doctor_id' => 'required|exists:users,id',
            'date' => 'required|date|after_or_equal:today',
            'duration' => 'nullable|integer|min:15|max:240'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $doctorId = $request->doctor_id;
        $date = $request->date;
        $duration = $request->duration ?? 30;

        // Get existing appointments for the doctor on this date
        $existingAppointments = Appointment::where('doctor_id', $doctorId)
            ->where('appointment_date', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->orderBy('appointment_time')
            ->get();

        // Generate available slots (8 AM to 6 PM in 30-minute intervals)
        $availableSlots = [];
        $startTime = Carbon::parse($date . ' 08:00:00');
        $endTime = Carbon::parse($date . ' 18:00:00');
        $currentTime = $startTime->copy();

        while ($currentTime->copy()->addMinutes($duration)->lte($endTime)) {
            $slotEnd = $currentTime->copy()->addMinutes($duration);
            
            // Check if this slot conflicts with existing appointments
            $hasConflict = $existingAppointments->some(function ($appointment) use ($currentTime, $slotEnd) {
                try {
                    // With model accessor, appointment_time should be clean "09:00" format
                    $appointmentStart = Carbon::createFromFormat('Y-m-d H:i', $appointment->appointment_date . ' ' . $appointment->appointment_time);
                    $appointmentEnd = $appointmentStart->copy()->addMinutes($appointment->duration ?? 30);
                    
                    return $currentTime->lt($appointmentEnd) && $slotEnd->gt($appointmentStart);
                } catch (\Exception $e) {
                    \Log::warning('Invalid datetime in available slots check', [
                        'appointment_id' => $appointment->id,
                        'date' => $appointment->appointment_date,
                        'time' => $appointment->appointment_time,
                        'error' => $e->getMessage()
                    ]);
                    return false;
                }
            });

            if (!$hasConflict) {
                $availableSlots[] = [
                    'start_time' => $currentTime->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'display_time' => $currentTime->format('g:i A') . ' - ' . $slotEnd->format('g:i A')
                ];
            }
            
            $currentTime->addMinutes(30); // Move to next 30-minute slot
        }

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'available_slots' => $availableSlots,
                'total_slots' => count($availableSlots),
                'existing_appointments' => $existingAppointments->map(function ($appointment) {
                    return [
                        'id' => $appointment->id,
                        'time' => $appointment->appointment_time,
                        'duration' => $appointment->duration,
                        'patient' => $appointment->patient->name ?? 'Unknown',
                        'agenda' => $appointment->agenda
                    ];
                })
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to get available slots',
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
                              $patientQuery->where('district', $user->district);
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
