<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Validator;
class DoctorController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role !== User::ROLE_DOCTOR) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only doctors can access this endpoint.'
                ], 403);
            }

            // Get statistics for doctor's district
            $patientsInDistrict = User::where('role', User::ROLE_PATIENT)
                                     ->where('district', $user->district)
                                     ->count();

            $doctorAppointments = Appointment::where('doctor_id', $user->id)
                                            ->whereDate('appointment_date', '>=', now()->toDateString())
                                            ->count();

            $todayAppointments = Appointment::where('doctor_id', $user->id)
                                           ->whereDate('appointment_date', now()->toDateString())
                                           ->count();

            // Get recent appointments
            $recentAppointments = Appointment::with(['patient'])
                                            ->where('doctor_id', $user->id)
                                            ->orderBy('appointment_date', 'desc')
                                            ->orderBy('appointment_time', 'desc')
                                            ->limit(5)
                                            ->get();

            $dashboardData = [
                'user' => [
                    'name' => $user->full_name,
                    'role' => $user->role,
                    'district' => $user->district_name
                ],
                'stats' => [
                    'patients_in_district' => $patientsInDistrict,
                    'upcoming_appointments' => $doctorAppointments,
                    'today_appointments' => $todayAppointments,
                    'district' => $user->district
                ],
                'recent_appointments' => $recentAppointments->map(function ($appointment) {
                    return [
                        'id' => $appointment->id,
                        'patient_name' => $appointment->patient->full_name,
                        'agenda' => $appointment->agenda,
                        'date' => $appointment->formatted_date,
                        'time' => $appointment->formatted_time,
                        'status' => $appointment->status
                    ];
                })
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
     * Get patients in doctor's assigned district
     */
    public function getPatients(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role !== User::ROLE_DOCTOR) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only doctors can access this endpoint.'
                ], 403);
            }

            $query = User::where('role', User::ROLE_PATIENT)
                        ->where('district', $user->district);

            // Apply search filter if provided
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
                });
            }

      
            $query->where('is_verified', true);
            

            $patients = $query->select([
                'id', 
                'first_name', 
                'last_name', 
                'middle_initial',
                'contact_number', 
                'email',
                'address',
                'municipality',
                'province',
                'barangay',
                'patient_type',
                'sex',
                'district',
                'birth_date',
                'is_verified',
                'created_at'
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);

            // Append district_name for each patient
            $patients->getCollection()->transform(function ($patient) {
                $patient->append('district_name');
                return $patient;
            });

            return response()->json([
                'success' => true,
                'data' => $patients,
                'district_info' => [
                    'doctor_district' => $user->district,
                    'district_name'   => $user->district_name,
                ]
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
     * Get appointments for the doctor
     */
    // public function getAppointments(Request $request)
    // {
    //     try {
    //         $user = $request->user();

    //         if ($user->role !== User::ROLE_DOCTOR) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Access denied. Only doctors can access this endpoint.'
    //             ], 403);
    //         }

    //         $query = Appointment::with(['patient', 'createdBy'])
    //                            ->where('doctor_id', $user->id);

    //         // Apply date filters
    //         if ($request->has('date_from')) {
    //             $query->where('appointment_date', '>=', $request->date_from);
    //         }

    //         if ($request->has('date_to')) {
    //             $query->where('appointment_date', '<=', $request->date_to);
    //         }

    //         // Apply status filter
    //         if ($request->has('status')) {
    //             $query->where('status', $request->status);
    //         }

    //         // Apply priority filter
    //         if ($request->has('priority')) {
    //             $query->where('priority', $request->priority);
    //         }

    //         // Default to showing upcoming appointments
    //         if (!$request->has('date_from') && !$request->has('show_all')) {
    //             $query->where('appointment_date', '>=', now()->toDateString());
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

    /**
 * Get appointments for the doctor with multiple patients support
 */
public function getAppointments(Request $request)
{
    try {
        $user = $request->user();

        if ($user->role !== User::ROLE_DOCTOR) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only doctors can access this endpoint.'
            ], 403);
        }

        // Load appointments with multiple patients relationship
        $query = Appointment::with(['patients', 'doctor', 'createdBy'])
                           ->where('doctor_id', $user->id);

        // Apply date filters
        if ($request->has('date_from')) {
            $query->where('appointment_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('appointment_date', '<=', $request->date_to);
        }

        // Apply status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Apply priority filter
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by multi-patient appointments only (optional)
        if ($request->has('multi_patient') && $request->boolean('multi_patient')) {
            $query->has('patients', '>', 1);
        }

        // Default to showing upcoming appointments
        if (!$request->has('date_from') && !$request->has('show_all')) {
            $query->where('appointment_date', '>=', now()->toDateString());
        }

        $appointments = $query->orderBy('appointment_date')
                             ->orderBy('appointment_time')
                             ->get();

        // Format appointments with multiple patients data
        $formattedAppointments = $appointments->map(function ($appointment) {
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
                        'municipality' => $patient->municipality ?? null,
                        'barangay' => $patient->barangay ?? null,
                        'patient_type' => $patient->patient_type ?? null
                    ];
                }),
                'patient_count' => $appointment->patients->count(),
                'patient_names' => $appointment->patient_names,
                
                // Backward compatibility - single patient data
                'patient_id' => $appointment->patient_id,
                'patient' => $appointment->patient ? 
                    $appointment->patient->first_name . ' ' . $appointment->patient->last_name : 
                    $appointment->patient_names,
                
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
            'data' => $formattedAppointments,
            'count' => $formattedAppointments->count(),
            'meta' => [
                'total_appointments' => $formattedAppointments->count(),
                'multi_patient_count' => $formattedAppointments->where('is_multi_patient', true)->count(),
                'single_patient_count' => $formattedAppointments->where('is_multi_patient', false)->count(),
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
     * Get patient details (only if patient is in doctor's district)
     */
    public function getPatientDetails(Request $request, $patientId)
    {
        try {
            $user = $request->user();

            if ($user->role !== User::ROLE_DOCTOR) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only doctors can access this endpoint.'
                ], 403);
            }

            $patient = User::where('id', $patientId)
                          ->where('role', User::ROLE_PATIENT)
                          ->where('district', $user->district)
                          ->first();

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found or not in your assigned district'
                ], 404);
            }

            // Get patient's appointment history with this doctor
            $appointmentHistory = Appointment::with(['createdBy'])
                                            ->where('patient_id', $patientId)
                                            ->where('doctor_id', $user->id)
                                            ->orderBy('appointment_date', 'desc')
                                            ->get();

            $patientData = [
                'patient' => [
                    'id' => $patient->id,
                    'name' => $patient->full_name,
                    'contact_number' => $patient->contact_number,
                    'email' => $patient->email,
                    'address' => $patient->address,
                    'sex' => $patient->sex,
                    'birth_date' => $patient->birth_date->format('Y-m-d'),
                    'age' => $patient->birth_date->age,
                    'district' => $patient->district_name,
                    'province' => $patient->province,
                    'is_verified' => $patient->is_verified
                ],
                'appointment_history' => $appointmentHistory,
                'stats' => [
                    'total_appointments' => $appointmentHistory->count(),
                    'completed_appointments' => $appointmentHistory->where('status', 'completed')->count(),
                    'cancelled_appointments' => $appointmentHistory->where('status', 'cancelled')->count(),
                    'last_appointment' => $appointmentHistory->first() ? $appointmentHistory->first()->formatted_date : null
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $patientData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient details',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get district statistics for the doctor
     */
    public function getDistrictStats(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role !== User::ROLE_DOCTOR) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only doctors can access this endpoint.'
                ], 403);
            }

            $stats = [
                'district_info' => [
                    'district' => $user->district,
                    'district_name' => $user->district_name
                ],
                'patients' => [
                    'total' => User::where('role', User::ROLE_PATIENT)
                                  ->where('district', $user->district)
                                  ->count(),
                    'verified' => User::where('role', User::ROLE_PATIENT)
                                     ->where('district', $user->district)
                                     ->where('is_verified', true)
                                     ->count(),
                    'new_this_month' => User::where('role', User::ROLE_PATIENT)
                                           ->where('district', $user->district)
                                           ->whereMonth('created_at', now()->month)
                                           ->whereYear('created_at', now()->year)
                                           ->count()
                ],
                'appointments' => [
                    'total_scheduled' => Appointment::where('doctor_id', $user->id)
                                                   ->where('status', '!=', 'completed')
                                                   ->where('status', '!=', 'cancelled')
                                                   ->count(),
                    'today' => Appointment::where('doctor_id', $user->id)
                                         ->whereDate('appointment_date', now()->toDateString())
                                         ->count(),
                    'this_week' => Appointment::where('doctor_id', $user->id)
                                             ->whereBetween('appointment_date', [
                                                 now()->startOfWeek()->toDateString(),
                                                 now()->endOfWeek()->toDateString()
                                             ])
                                             ->count(),
                    'completed_this_month' => Appointment::where('doctor_id', $user->id)
                                                        ->where('status', 'completed')
                                                        ->whereMonth('appointment_date', now()->month)
                                                        ->whereYear('appointment_date', now()->year)
                                                        ->count()
                ],
                'other_doctors_in_district' => User::where('role', User::ROLE_DOCTOR)
                                                  ->where('district', $user->district)
                                                  ->where('id', '!=', $user->id)
                                                  ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve district statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function confirmAppointment(Request $request, Appointment $appointment)
{
    try {
        $appointment->update([
            'status' => 'confirmed',
            'updated_by' => $request->user()->id
        ]);

        $this->notificationService->sendAppointmentNotifications($appointment, 'confirmed');

        return response()->json([
            'success' => true,
            'message' => 'Appointment confirmed successfully',
            'data' => $appointment->load(['patient', 'doctor'])
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to confirm appointment',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

/**
 * Start appointment
 */
public function startAppointment(Request $request, Appointment $appointment)
{
    try {
        $appointment->update([
            'status' => 'in_progress',
            'updated_by' => $request->user()->id
        ]);

        $this->notificationService->sendAppointmentNotifications($appointment, 'in_progress');

        return response()->json([
            'success' => true,
            'message' => 'Appointment started successfully',
            'data' => $appointment->load(['patient', 'doctor'])
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to start appointment',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

/**
 * Complete appointment
 */
public function completeAppointment(Request $request, Appointment $appointment)
{
    try {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $appointment->update([
            'status' => 'completed',
            'notes' => $request->notes,
            'updated_by' => $request->user()->id
        ]);

        $this->notificationService->sendAppointmentNotifications($appointment, 'completed');

        return response()->json([
            'success' => true,
            'message' => 'Appointment completed successfully',
            'data' => $appointment->load(['patient', 'doctor'])
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to complete appointment',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}
}