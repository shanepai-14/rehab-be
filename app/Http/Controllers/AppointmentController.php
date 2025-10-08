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

// public function create(Request $request)
// {
//     try {
//         $user = $request->user();

//         // Check if user has permission to create appointments
//         if (!in_array($user->role, [User::ROLE_DOCTOR, User::ROLE_ADMIN])) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Unauthorized. Only doctors and admins can create appointments.'
//             ], 403);
//         }

//         $validator = Validator::make($request->all(), [
//             'patient_id' => 'required|exists:users,id',
//             'agenda' => 'required|string|max:255',
//             'details' => 'required|string',
//             'appointment_date' => 'required|date|after_or_equal:today',
//             'appointment_time' => 'required|date_format:H:i',
//             'location' => 'nullable|string|max:255',
//             'duration' => 'nullable|integer|min:15|max:240', // in minutes
//             'priority' => 'nullable|in:low,normal,high,urgent',
//             'doctor_id' => 'nullable|exists:users,id' // For admin creating appointments
//         ]);

//         if ($validator->fails()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Validation failed',
//                 'errors' => $validator->errors()
//             ], 422);
//         }

//         // Get patient details
//         $patient = User::where('id', $request->patient_id)
//                        ->where('role', User::ROLE_PATIENT)
//                        ->first();

//         if (!$patient) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Patient not found'
//             ], 404);
//         }

//         // Determine the doctor for this appointment
//         $doctorId = $user->role === User::ROLE_DOCTOR ? $user->id : $request->doctor_id;
        
//         if (!$doctorId) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Doctor ID is required for admin-created appointments'
//             ], 422);
//         }

//         // Validate doctor exists and has correct role
//         $doctor = User::where('id', $doctorId)
//                      ->where('role', User::ROLE_DOCTOR)
//                      ->first();

//         if (!$doctor) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Doctor not found'
//             ], 404);
//         }

//         // For doctors, check if patient is in their assigned district
//         if ($user->role === User::ROLE_DOCTOR) {
//             if (!$this->canDoctorAccessPatient($user, $patient)) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'You can only create appointments for patients in your assigned district.'
//                 ], 403);
//             }
//         }

//         // Calculate appointment duration and end time
//         $duration = $request->duration ?? 30; // Default 30 minutes
        
//         // Normalize time format - remove seconds if present to match database format
//         $appointmentTime = $request->appointment_time;
//         if (strlen($appointmentTime) > 5) {
//             $appointmentTime = substr($appointmentTime, 0, 5); // Convert "09:00:00" to "09:00"
//         }
        
//         // Properly parse the date and time
//         try {
//             $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i', $request->appointment_date . ' ' . $appointmentTime);
//             $appointmentEndTime = $appointmentDateTime->copy()->addMinutes($duration);
            
      
            
//         } catch (\Exception $e) {
    
            
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Invalid date or time format',
//                 'error' => 'Please ensure date is in YYYY-MM-DD format and time is in HH:MM or HH:MM:SS format'
//             ], 422);
//         }

//         // Check for schedule overlaps
//         $overlapCheck = $this->checkScheduleOverlap(
//             $doctorId,
//             $request->patient_id,
//             $appointmentDateTime,
//             $appointmentEndTime,
//             null // No appointment ID for new appointments
//         );

//         if (!$overlapCheck['success']) {
//             return response()->json($overlapCheck, 409); // 409 Conflict
//         }

//         // Validate business hours (8 AM to 6 PM)
//         $startHour = $appointmentDateTime->hour;
//         $endHour = $appointmentEndTime->hour;
//         $endMinute = $appointmentEndTime->minute;

//         if ($startHour < 8 || $endHour > 18 || ($endHour === 18 && $endMinute > 0)) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Appointments must be scheduled between 8:00 AM and 6:00 PM'
//             ], 422);
//         }

//         // Check if appointment is on a weekend (optional business rule)
//         if ($appointmentDateTime->isWeekend()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Appointments cannot be scheduled on weekends'
//             ], 422);
//         }

//         // Create appointment
//         DB::beginTransaction();

//         $appointment = Appointment::create([
//             'patient_id' => $request->patient_id,
//             'doctor_id' => $doctorId,
//             'created_by' => $user->id,
//             'agenda' => $request->agenda,
//             'details' => $request->details,
//             'appointment_date' => $request->appointment_date,
//             'appointment_time' => $request->appointment_time,
//             'location' => $request->location,
//             'duration' => $duration,
//             'priority' => $request->priority ?? 'normal',
//             'status' => 'scheduled'
//         ]);

//         DB::commit();

//         // Send notifications (uncomment when ready)
//         $this->sendAppointmentNotifications($appointment, 'created');

//         return response()->json([
//             'success' => true,
//             'message' => 'Appointment created successfully',
//             'data' => [
//                 'appointment' => $appointment->load(['patient', 'doctor', 'createdBy'])
//             ]
//         ], 201);

//     } catch (\Exception $e) {
//         DB::rollBack();
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to create appointment',
//             'error' => config('app.debug') ? $e->getMessage() : null
//         ], 500);
//     }
// }

public function create(Request $request)
    {
        // Update validation to accept patient_ids array
        $validator = Validator::make($request->all(), [
            'patient_ids' => 'required|array|min:1',
            'patient_ids.*' => 'required|exists:users,id',
            'doctor_id' => 'nullable|exists:users,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'agenda' => 'required|string|max:255',
            'details' => 'nullable|string',
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

        DB::beginTransaction();
        try {
            $user = $request->user();

             if (!in_array($user->role, [User::ROLE_DOCTOR, User::ROLE_ADMIN])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only doctors and admins can create appointments.'
            ], 403);
        }

            $patientIds = $request->patient_ids;
            
            // Verify all patients exist and are actually patients
            $patients = User::whereIn('id', $patientIds)
                           ->where('role', 'patient')
                           ->get();
            
            if ($patients->count() !== count($patientIds)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'One or more selected users are not valid patients'
                ], 422);
            }

            // Create datetime for overlap checking
            $appointmentDate = Carbon::parse($request->appointment_date);
            $appointmentTime = Carbon::parse($request->appointment_time);
            $startDateTime = Carbon::parse($appointmentDate->format('Y-m-d') . ' ' . $appointmentTime->format('H:i'));
            $duration = $request->duration ?? 30;
            $endDateTime = $startDateTime->copy()->addMinutes($duration);

            // Check for doctor schedule conflicts
            $doctorId = $request->doctor_id ?? $user->id;
            $doctorConflict = $this->checkScheduleOverlap($doctorId, null, $startDateTime, $endDateTime);
            if ($doctorConflict) {
                DB::rollBack();
                return response()->json($doctorConflict, 409);
            }

            // Check for patient schedule conflicts for EACH patient
            foreach ($patientIds as $patientId) {
                $patientConflict = $this->checkScheduleOverlap(null, $patientId, $startDateTime, $endDateTime);
                if ($patientConflict) {
                    DB::rollBack();
                    return response()->json($patientConflict, 409);
                }
            }

            // Create the appointment (patient_id is first patient for backward compatibility)
            $appointment = Appointment::create([
                'patient_id' => $patientIds[0], // For backward compatibility
                'doctor_id' => $doctorId,
                'created_by' => $user->id,
                'appointment_date' => $request->appointment_date,
                'appointment_time' => $request->appointment_time,
                'agenda' => $request->agenda,
                'details' => $request->details ?? '',
                'location' => $request->location,
                'duration' => $duration,
                'priority' => $request->priority ?? 'normal',
                'status' => 'scheduled'
            ]);

            // Attach all patients to the appointment via pivot table
            $appointment->patients()->attach($patientIds);

            DB::commit();

            // Load relationships for response
            $appointment->load(['patients', 'doctor', 'createdBy']);

            // Send notifications
            $this->sendAppointmentNotifications($appointment, 'created');

            return response()->json([
                'success' => true,
                'message' => 'Appointment created successfully with ' . count($patientIds) . ' patient(s)',
                'data' => [
                    'appointment' => $appointment
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
// private function checkScheduleOverlap($doctorId, $patientId, $startDateTime, $endDateTime, $excludeAppointmentId = null)
// {


//     // Check doctor schedule conflicts
//     $doctorAppointments = Appointment::where('doctor_id', $doctorId)
//         ->where('appointment_date', $startDateTime->format('Y-m-d'))
//         ->whereNotIn('status', ['cancelled', 'no_show'])
//         ->when($excludeAppointmentId, function ($query) use ($excludeAppointmentId) {
//             return $query->where('id', '!=', $excludeAppointmentId);
//         })
//         ->with(['patient'])
//         ->get();


//     $doctorConflicts = $doctorAppointments->filter(function ($appointment) use ($startDateTime, $endDateTime) {
//         try {
//             // With the model accessor, appointment_time should now be clean "09:00" format
//             $existingStart = Carbon::createFromFormat('Y-m-d H:i', $appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time);
//             $existingEnd = $existingStart->copy()->addMinutes($appointment->duration ?? 30);
            

            
//             // Check for overlap: new appointment overlaps if it starts before existing ends 
//             // AND ends after existing starts
//             return $startDateTime->lt($existingEnd) && $endDateTime->gt($existingStart);
            
//         } catch (\Exception $e) {

//             return false;
//         }
//     });

//     if ($doctorConflicts->isNotEmpty()) {
//         $conflictTime = $doctorConflicts->first();
        
  
        
//         try {
//             $conflictStart = Carbon::createFromFormat('Y-m-d H:i', $conflictTime->appointment_date . ' ' . $conflictTime->appointment_time);
            
//             return [
//                 'success' => false,
//                 'message' => 'Doctor schedule conflict detected',
//                 'details' => [
//                     'conflict_type' => 'doctor',
//                     'existing_appointment' => [
//                         'id' => $conflictTime->id,
//                         'patient' => $conflictTime->patient->first_name . ' ' . $conflictTime->patient->last_name,
//                         'time' => $conflictStart->format('M j, Y g:i A'),
//                         'duration' => $conflictTime->duration . ' minutes',
//                         'agenda' => $conflictTime->agenda ?? 'Unknown'
//                     ]
//                 ]
//             ];
//         } catch (\Exception $e) {
            
//             return [
//                 'success' => false,
//                 'message' => 'Doctor schedule conflict detected',
//                 'details' => [
//                     'conflict_type' => 'doctor',
//                     'existing_appointment' => [
//                         'id' => $conflictTime->id,
//                         'patient' => $conflictTime->patient->first_name . ' ' . $conflictTime->patient->last_name,
//                         'raw_time' => $conflictTime->appointment_time,
//                         'duration' => $conflictTime->duration . ' minutes'
//                     ]
//                 ]
//             ];
//         }
//     }

//     // Check patient schedule conflicts
//     $patientAppointments = Appointment::where('patient_id', $patientId)
//         ->where('appointment_date', $startDateTime->format('Y-m-d'))
//         ->whereNotIn('status', ['cancelled', 'no_show'])
//         ->when($excludeAppointmentId, function ($query) use ($excludeAppointmentId) {
//             return $query->where('id', '!=', $excludeAppointmentId);
//         })
//         ->with(['doctor'])
//         ->get();



//     $patientConflicts = $patientAppointments->filter(function ($appointment) use ($startDateTime, $endDateTime) {
//         try {
//             // With the model accessor, appointment_time should now be clean "09:00" format
//             $existingStart = Carbon::createFromFormat('Y-m-d H:i', $appointment->appointment_date . ' ' . $appointment->appointment_time);
//             $existingEnd = $existingStart->copy()->addMinutes($appointment->duration ?? 30);
            
 
//             return $startDateTime->lt($existingEnd) && $endDateTime->gt($existingStart);
            
//         } catch (\Exception $e) {

//             return false;
//         }
//     });

//     if ($patientConflicts->isNotEmpty()) {
//         $conflictTime = $patientConflicts->first();
        

        
//         try {
//             $conflictStart = Carbon::createFromFormat('Y-m-d H:i', $conflictTime->appointment_date . ' ' . $conflictTime->appointment_time);
            
//             return [
//                 'success' => false,
//                 'message' => 'Patient schedule conflict detected',
//                 'details' => [
//                     'conflict_type' => 'patient',
//                     'existing_appointment' => [
//                         'id' => $conflictTime->id,
//                         'doctor' => $conflictTime->doctor->first_name . ' ' . $conflictTime->doctor->last_name,
//                         'time' => $conflictStart->format('M j, Y g:i A'),
//                         'duration' => $conflictTime->duration . ' minutes',
//                         'agenda' => $conflictTime->agenda ?? 'Unknown'
//                     ]
//                 ]
//             ];
//         } catch (\Exception $e) {
//             return [
//                 'success' => false,
//                 'message' => 'Patient schedule conflict detected',
//                 'details' => [
//                     'conflict_type' => 'patient',
//                     'existing_appointment' => [
//                         'id' => $conflictTime->id,
//                         'doctor' => $conflictTime->doctor->first_name . ' ' . $conflictTime->doctor->last_name,
//                         'raw_time' => $conflictTime->appointment_time,
//                         'duration' => $conflictTime->duration . ' minutes'
//                     ]
//                 ]
//             ];
//         }
//     }

//     return ['success' => true];
// }

private function checkScheduleOverlap($doctorId = null, $patientId = null, $startDateTime, $endDateTime, $excludeAppointmentId = null)
    {
        if ($doctorId) {
            // Check doctor schedule conflicts
            $appointments = Appointment::where('doctor_id', $doctorId)
                ->where('appointment_date', $startDateTime->format('Y-m-d'))
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->when($excludeAppointmentId, function ($query) use ($excludeAppointmentId) {
                    return $query->where('id', '!=', $excludeAppointmentId);
                })
                ->with(['patients'])
                ->get();

            foreach ($appointments as $appointment) {
                try {
                    $existingStart = Carbon::createFromFormat('Y-m-d H:i', 
                        $appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time);
                    $existingEnd = $existingStart->copy()->addMinutes($appointment->duration ?? 30);

                    if ($startDateTime->lt($existingEnd) && $endDateTime->gt($existingStart)) {
                        $patientNames = $appointment->patients->pluck('first_name')->join(', ') ?: 'Unknown';
                        return [
                            'success' => false,
                            'message' => 'Doctor schedule conflict detected',
                            'details' => [
                                'conflict_type' => 'doctor',
                                'existing_appointment' => [
                                    'id' => $appointment->id,
                                    'patients' => $patientNames,
                                    'time' => $existingStart->format('M j, Y g:i A'),
                                    'duration' => $appointment->duration . ' minutes'
                                ]
                            ]
                        ];
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        if ($patientId) {
            // Check patient schedule conflicts (check both old patient_id and new pivot table)
            $appointments = Appointment::where(function($query) use ($patientId) {
                    $query->where('patient_id', $patientId)
                          ->orWhereHas('patients', function($q) use ($patientId) {
                              $q->where('users.id', $patientId);
                          });
                })
                ->where('appointment_date', $startDateTime->format('Y-m-d'))
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->when($excludeAppointmentId, function ($query) use ($excludeAppointmentId) {
                    return $query->where('id', '!=', $excludeAppointmentId);
                })
                ->with('doctor')
                ->get();

            foreach ($appointments as $appointment) {
                try {
                    $existingStart = Carbon::createFromFormat('Y-m-d H:i', 
                        $appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time);
                    $existingEnd = $existingStart->copy()->addMinutes($appointment->duration ?? 30);

                    if ($startDateTime->lt($existingEnd) && $endDateTime->gt($existingStart)) {
                        $patient = User::find($patientId);
                        return [
                            'success' => false,
                            'message' => 'Patient schedule conflict detected',
                            'details' => [
                                'conflict_type' => 'patient',
                                'patient_name' => $patient->first_name . ' ' . $patient->last_name,
                                'existing_appointment' => [
                                    'id' => $appointment->id,
                                    'doctor' => $appointment->doctor->first_name . ' ' . $appointment->doctor->last_name,
                                    'time' => $existingStart->format('M j, Y g:i A'),
                                    'duration' => $appointment->duration . ' minutes'
                                ]
                            ]
                        ];
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return null;
    }

    public function getAppointmentPatients(Appointment $appointment)
    {
        try {
            $patients = $appointment->patients()->get();

            return response()->json([
                'success' => true,
                'data' => $patients,
                'count' => $patients->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve appointment patients',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
    // public function index(Request $request)
    // {
    //     try {
    //         $user = $request->user();
    //         $query = Appointment::with(['patient', 'doctor', 'createdBy']);

    //         // Apply role-based filtering
    //         switch ($user->role) {
    //             case User::ROLE_PATIENT:
    //                 $query->where('patient_id', $user->id);
    //                 break;

    //             case User::ROLE_DOCTOR:
    //                 // Doctors can see appointments for patients in their district
    //                 $query->where(function ($q) use ($user) {
    //                     $q->where('doctor_id', $user->id)
    //                       ->orWhereHas('patient', function ($patientQuery) use ($user) {
    //                           $patientQuery->where('district', $user->district);
    //                       });
    //                 });
    //                 break;

    //             case User::ROLE_ADMIN:
    //                 // Admins can see all appointments
    //                 break;

    //             default:
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Unauthorized access'
    //                 ], 403);
    //         }

    //         // Apply filters
    //         if ($request->has('date_from')) {
    //             $query->where('appointment_date', '>=', $request->date_from);
    //         }

    //         if ($request->has('date_to')) {
    //             $query->where('appointment_date', '<=', $request->date_to);
    //         }

    //         if ($request->has('status')) {
    //             $query->where('status', $request->status);
    //         }

    //         if ($request->has('priority')) {
    //             $query->where('priority', $request->priority);
    //         }

    //         $appointments = $query->orderBy('appointment_date')
    //                              ->orderBy('appointment_time')
    //                              ->paginate(15);

    //         return response()->json([
    //             'success' => true,
    //             'data' => $appointments
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to retrieve appointments',
    //             'error' => config('app.debug') ? $e->getMessage() : null
    //         ], 500);
    //     }
    // }

    public function index(Request $request)
{
    try {
        $user = $request->user();
        
        // Load appointments with multiple patients relationship
        $query = Appointment::with(['patients', 'patient', 'doctor', 'createdBy']);

        // Apply role-based filtering
        switch ($user->role) {
            case User::ROLE_PATIENT:
                // Patients can see appointments where they are included
                // Check both old patient_id field and new patients relationship
                $query->where(function($q) use ($user) {
                    $q->where('patient_id', $user->id)
                      ->orWhereHas('patients', function($patientQuery) use ($user) {
                          $patientQuery->where('users.id', $user->id);
                      });
                });
                break;

            case User::ROLE_DOCTOR:
                // Doctors can see appointments assigned to them or patients in their district
                $query->where(function ($q) use ($user) {
                    $q->where('doctor_id', $user->id)
                      ->orWhereHas('patient', function ($patientQuery) use ($user) {
                          $patientQuery->where('district', $user->district);
                      })
                      ->orWhereHas('patients', function ($patientsQuery) use ($user) {
                          $patientsQuery->where('district', $user->district);
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

        // Filter by multi-patient appointments (optional)
        if ($request->has('multi_patient') && $request->boolean('multi_patient')) {
            $query->has('patients', '>', 1);
        }

        // Get paginated results
        $appointments = $query->orderBy('appointment_date')
                             ->orderBy('appointment_time')
                             ->paginate(15);

        // Transform the data to include multi-patient information
        $appointments->getCollection()->transform(function ($appointment) {
            return [
                'id' => $appointment->id,
                
                // Multiple patients data
                'patients' => $appointment->patients->map(function ($patient) {
                    return [
                        'id' => $patient->id,
                        'name' => $patient->first_name . ' ' . $patient->last_name,
                        'first_name' => $patient->first_name,
                        'last_name' => $patient->last_name,
                        'contact_number' => $patient->contact_number,
                        'district' => $patient->district,
                        'municipality' => $patient->municipality ?? null
                    ];
                }),
                'patient_count' => $appointment->patients->count(),
                'patient_names' => $appointment->patient_names,
                
                // Backward compatibility
                'patient_id' => $appointment->patient_id,
                'patient' => $appointment->patient ? 
                    $appointment->patient->first_name . ' ' . $appointment->patient->last_name : 
                    $appointment->patient_names,
                
                // Doctor information
                'doctor_id' => $appointment->doctor_id,
                'doctor' => $appointment->doctor ? 
                    'Dr. ' . $appointment->doctor->first_name . ' ' . $appointment->doctor->last_name : 
                    'Unassigned',
                'doctor_specialization' => $appointment->doctor->specialization ?? 'General Medicine',
                
                // Appointment details
                'agenda' => $appointment->agenda,
                'details' => $appointment->details,
                'appointment_date' => $appointment->appointment_date->format('Y-m-d'),
                'formatted_date' => $appointment->formatted_date,
                'appointment_time' => $appointment->formatted_time,
                'raw_time' => $appointment->appointment_time,
                'location' => $appointment->location,
                'duration' => $appointment->duration,
                'priority' => $appointment->priority,
                'status' => $appointment->status,
                'notes' => $appointment->notes,
                
                // Meta information
                'is_multi_patient' => $appointment->is_multi_patient,
                'is_today' => $appointment->isToday(),
                'is_upcoming' => $appointment->isUpcoming(),
                'status_color' => $appointment->status_color,
                'priority_color' => $appointment->priority_color,
                
                // Timestamps
                'created_at' => $appointment->created_at,
                'updated_at' => $appointment->updated_at,
                'created_by' => $appointment->createdBy->first_name ?? 'System'
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $appointments,
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
                'multi_patient_count' => $appointments->where('is_multi_patient', true)->count(),
            ]
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
// public function update(Request $request, Appointment $appointment)
// {
//     try {
//         $user = $request->user();

//         $validator = Validator::make($request->all(), [
//             'agenda' => 'sometimes|required|string|max:255',
//             'details' => 'sometimes|required|string',
//             'appointment_date' => 'sometimes|required|date|after:now',
//             'appointment_time' => 'sometimes|required|date_format:H:i',
//             'location' => 'nullable|string|max:255',
//             'duration' => 'nullable|integer|min:15|max:240',
//             'priority' => 'nullable|in:low,normal,high,urgent'
//         ]);

//         if ($validator->fails()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Validation failed',
//                 'errors' => $validator->errors()
//             ], 422);
//         }

//         $oldData = $appointment->toArray();
        
//         $appointment->update(array_merge(
//             $request->only(['agenda', 'details', 'appointment_date', 'appointment_time', 'location', 'duration', 'priority']),
//             ['updated_by' => $user->id]
//         ));

//         // Send notification if significant changes were made
//         $significantFields = ['appointment_date', 'appointment_time', 'agenda'];
//         $hasSignificantChanges = collect($significantFields)->some(function ($field) use ($request, $oldData) {
//             return $request->has($field) && $request->get($field) != $oldData[$field];
//         });

//         if ($hasSignificantChanges) {
//             $this->notificationService->sendAppointmentNotifications($appointment, 'updated');
//         }

//         return response()->json([
//             'success' => true,
//             'message' => 'Appointment updated successfully',
//             'data' => $appointment->load(['patient', 'doctor', 'updatedBy'])
//         ]);

//     } catch (\Exception $e) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to update appointment',
//             'error' => config('app.debug') ? $e->getMessage() : null
//         ], 500);
//     }
// }

  public function update(Request $request, Appointment $appointment)
    {
        $validator = Validator::make($request->all(), [
            'patient_ids' => 'nullable|array|min:1',
            'patient_ids.*' => 'exists:users,id',
            'doctor_id' => 'nullable|exists:users,id',
            'agenda' => 'sometimes|required|string|max:255',
            'details' => 'sometimes|required|string',
            'status' => 'sometimes|required|string',
            'appointment_date' => 'sometimes|required|date|after_or_equal:today',
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

        DB::beginTransaction();
        try {
            $user = $request->user();
            
            // If updating patients
            if ($request->has('patient_ids')) {
                $patientIds = $request->patient_ids;
                
                // Verify all patients exist and are actually patients
                $patients = User::whereIn('id', $patientIds)
                               ->where('role', 'patient')
                               ->get();
                
                if ($patients->count() !== count($patientIds)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'One or more selected users are not valid patients'
                    ], 422);
                }
                
                // Sync patients (removes old, adds new)
                $appointment->patients()->sync($patientIds);
                
                // Update patient_id for backward compatibility
                $appointment->patient_id = $patientIds[0];
            }

            $oldData = $appointment->toArray();
            
            // Update appointment fields
            $appointment->fill($request->only([
                'doctor_id', 'appointment_date', 'appointment_time',
                'agenda', 'details', 'location', 'duration', 'priority', 'status'
            ]));
            $appointment->updated_by = $user->id;
            $appointment->save();

            DB::commit();

            // Send notification if significant changes were made
            $significantFields = ['appointment_date', 'appointment_time', 'agenda'];
            $hasSignificantChanges = collect($significantFields)->some(function ($field) use ($request, $oldData) {
                return $request->has($field) && $request->get($field) != $oldData[$field];
            });

            if ($hasSignificantChanges) {
                $this->sendAppointmentNotifications($appointment, 'updated');
            }

            // Load relationships for response
            $appointment->load(['patients', 'doctor', 'updatedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Appointment updated successfully',
                'data' => $appointment
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
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
