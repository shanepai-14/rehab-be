<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationTestController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Test SMS functionality
     */
    public function testSms(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'message' => 'nullable|string|max:160'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = $request->phone;
        $message = $request->message ?? 'This is a test message from HealthApp notification system.';

        $result = $this->notificationService->testSms($phone, $message);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Test SMS sent successfully' : 'Failed to send test SMS',
            'data' => [
                'phone' => $phone,
                'message' => $message,
                'timestamp' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Test real-time notification
     */
    public function testRealtime(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'appointment_id' => 'required|exists:appointments,id',
            'event' => 'required|in:created,updated,confirmed,cancelled,reminder,status_changed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $appointment = Appointment::with(['patient', 'doctor'])->findOrFail($request->appointment_id);
            
            $this->notificationService->sendRealtimeNotification(
                $appointment, 
                $request->event,
                $request->old_status
            );

            return response()->json([
                'success' => true,
                'message' => 'Complete notification sent successfully (SMS + Real-time)',
                'data' => [
                    'appointment_id' => $appointment->id,
                    'event' => $request->event,
                    'patient' => $appointment->patient->full_name,
                    'patient_phone' => $appointment->patient->contact_number,
                    'doctor' => $appointment->doctor ? $appointment->doctor->full_name : null,
                    'doctor_phone' => $appointment->doctor ? $appointment->doctor->contact_number : null,
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send appointment notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification system status
     */
    public function getStatus()
    {
        try {
            $smsStatus = $this->notificationService->getSmsStatus();
            $broadcastingStatus = $this->notificationService->getBroadcastingStatus();

            return response()->json([
                'success' => true,
                'data' => [
                    'sms' => $smsStatus,
                    'broadcasting' => $broadcastingStatus,
                    'system_time' => now()->toISOString(),
                    'timezone' => config('app.timezone')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get notification status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send test reminders manually
     */
    public function testReminders(Request $request)
    {
        try {
            $result = $this->notificationService->sendAppointmentReminders();

            return response()->json([
                'success' => true,
                'message' => 'Test reminders process completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test reminders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming appointments for testing
     */
    public function getUpcomingAppointments(Request $request)
    {
        try {
            $date = $request->get('date', now()->addDay()->toDateString());
            
            $appointments = Appointment::with(['patient', 'doctor'])
                ->where('appointment_date', $date)
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->get()
                ->map(function ($appointment) {
                    return [
                        'id' => $appointment->id,
                        'patient' => $appointment->patient->full_name,
                        'patient_phone' => $appointment->patient->contact_number,
                        'doctor' => $appointment->doctor ? $appointment->doctor->full_name : null,
                        'doctor_phone' => $appointment->doctor ? $appointment->doctor->contact_number : null,
                        'agenda' => $appointment->agenda,
                        'date' => $appointment->formatted_date,
                        'time' => $appointment->formatted_time,
                        'status' => $appointment->status,
                        'priority' => $appointment->priority,
                        'location' => $appointment->location
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'count' => $appointments->count(),
                    'appointments' => $appointments
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get upcoming appointments',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}