<?php

// ========== NOTIFICATION SERVICE ==========
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\Appointment;
use App\Events\AppointmentCreated;
use App\Events\AppointmentUpdated;
use App\Events\AppointmentStatusChanged;
use App\Events\AppointmentReminder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send all notifications for appointment events
     */
    public function sendAppointmentNotifications(Appointment $appointment, string $event, string $oldStatus = null)
    {
        // Send SMS notification
        $this->sendSmsNotification($appointment, $event);

        // Send real-time notification via Laravel Events/Broadcasting
        $this->sendRealtimeNotification($appointment, $event, $oldStatus);
    }

    /**
     * Send SMS notification via Movider
     */
    public function sendSmsNotification(Appointment $appointment, string $event)
    {
        if (!config('services.movider.enabled', true)) {
            Log::info('SMS notifications are disabled');
            return;
        }

        $message = $this->generateSmsMessage($appointment, $event);
        $recipients = [$appointment->patient->contact_number];

        // Add doctor's number for certain events
        if (in_array($event, ['created', 'cancelled', 'confirmed']) && $appointment->doctor && $appointment->doctor->contact_number) {
            $recipients[] = $appointment->doctor->contact_number;
        }

        foreach ($recipients as $phone) {
            $this->sendMoviderSms($phone, $message, $appointment->id, $event);
        }
    }

    /**
     * Send real-time notification via Laravel Events/Broadcasting
     */
    public function sendRealtimeNotification(Appointment $appointment, string $event, string $oldStatus = null)
    {
        if (!config('broadcasting.default') || config('broadcasting.default') === 'null') {
            Log::info('Broadcasting is disabled');
            return;
        }

        try {
            switch ($event) {
                case 'created':
                    event(new AppointmentCreated($appointment));
                    break;
                
                case 'status_changed':
                    if ($oldStatus) {
                        event(new AppointmentStatusChanged($appointment, $oldStatus, $appointment->status));
                    } else {
                        event(new AppointmentUpdated($appointment, $event));
                    }
                    break;
                
                case 'reminder':
                    event(new AppointmentReminder($appointment));
                    break;
                
                case 'confirmed':
                case 'cancelled':
                case 'completed':
                case 'in_progress':
                case 'updated':
                default:
                    event(new AppointmentUpdated($appointment, $event));
                    break;
            }

            Log::info('Real-time notification sent', [
                'appointment_id' => $appointment->id,
                'event' => $event
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send real-time notification', [
                'appointment_id' => $appointment->id,
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send SMS via Movider API
     */
    protected function sendMoviderSms(string $phone, string $message, int $appointmentId = null, string $event = null)
    {
        try {
            // Clean phone number (remove spaces, dashes, etc.)
            $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
            
            // Ensure phone number starts with country code for Philippines
            if (substr($cleanPhone, 0, 1) === '0') {
                $cleanPhone = '+63' . substr($cleanPhone, 1);
            } elseif (substr($cleanPhone, 0, 3) !== '+63') {
                $cleanPhone = '+63' . $cleanPhone;
            }

            $response = Http::timeout(30)->post(config('services.movider.endpoint'), [
                'username' => config('services.movider.username'),
                'password' => config('services.movider.password'),
                'to' => $cleanPhone,
                'text' => $message,
                'from' => config('services.movider.sender_id', 'HealthApp')
            ]);

            if ($response->successful()) {
                Log::info('SMS sent successfully', [
                    'phone' => $cleanPhone,
                    'appointment_id' => $appointmentId,
                    'event' => $event,
                    'response' => $response->json()
                ]);
                return true;
            } else {
                Log::error('Failed to send SMS - API Error', [
                    'phone' => $cleanPhone,
                    'appointment_id' => $appointmentId,
                    'event' => $event,
                    'status_code' => $response->status(),
                    'response_body' => $response->body()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('SMS sending error - Exception', [
                'phone' => $phone,
                'appointment_id' => $appointmentId,
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Generate SMS message based on appointment and event
     */
    protected function generateSmsMessage(Appointment $appointment, string $event): string
    {
        $doctorName = $appointment->doctor ? $appointment->doctor->full_name : 'TBA';
        $patientName = $appointment->patient->full_name;
        $date = $appointment->formatted_date;
        $time = $appointment->formatted_time;
        $agenda = $appointment->agenda;
        $location = $appointment->location ? " at {$appointment->location}" : '';

        $messages = [
            'created' => "Hi {$patientName}, your appointment has been scheduled for {$date} at {$time} with Dr. {$doctorName}. Agenda: {$agenda}{$location}. Please confirm your attendance.",
            
            'confirmed' => "Hi {$patientName}, your appointment on {$date} at {$time} with Dr. {$doctorName} has been CONFIRMED{$location}. See you there!",
            
            'cancelled' => "Hi {$patientName}, your appointment on {$date} at {$time} with Dr. {$doctorName} has been CANCELLED. Please contact us to reschedule.",
            
            'completed' => "Hi {$patientName}, your appointment with Dr. {$doctorName} has been completed. Thank you for your visit!",
            
            'in_progress' => "Hi {$patientName}, your appointment with Dr. {$doctorName} is now in progress.",
            
            'reminder' => "REMINDER: Hi {$patientName}, you have an appointment TOMORROW at {$time} with Dr. {$doctorName}. Agenda: {$agenda}{$location}. Don't forget!",
            
            'status_changed' => "Hi {$patientName}, your appointment on {$date} at {$time} status has been updated to: {$appointment->status}.",
            
            'updated' => "Hi {$patientName}, your appointment details have been updated. Date: {$date}, Time: {$time}, Doctor: Dr. {$doctorName}. Agenda: {$agenda}{$location}."
        ];

        return $messages[$event] ?? "Appointment Update: {$agenda} on {$date} at {$time}";
    }

    /**
     * Send bulk appointment reminders (called by scheduled command)
     */
    public function sendAppointmentReminders()
    {
        $tomorrow = now()->addDay()->toDateString();
        
        $upcomingAppointments = Appointment::with(['patient', 'doctor'])
            ->where('appointment_date', $tomorrow)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->get();

        Log::info("Found {$upcomingAppointments->count()} appointments for tomorrow", [
            'date' => $tomorrow
        ]);

        $successCount = 0;
        $failureCount = 0;

        foreach ($upcomingAppointments as $appointment) {
            try {
                $this->sendAppointmentNotifications($appointment, 'reminder');
                $successCount++;
                
                Log::info("Reminder sent for appointment", [
                    'appointment_id' => $appointment->id,
                    'patient' => $appointment->patient->full_name,
                    'doctor' => $appointment->doctor ? $appointment->doctor->full_name : null,
                    'date' => $appointment->appointment_date,
                    'time' => $appointment->appointment_time
                ]);
                
            } catch (\Exception $e) {
                $failureCount++;
                Log::error("Failed to send reminder for appointment", [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info("Appointment reminders completed", [
            'total' => $upcomingAppointments->count(),
            'success' => $successCount,
            'failed' => $failureCount
        ]);

        return [
            'total' => $upcomingAppointments->count(),
            'success' => $successCount,
            'failed' => $failureCount
        ];
    }

    /**
     * Test SMS functionality
     */
    public function testSms(string $phone, string $message = 'This is a test message from HealthApp.')
    {
        return $this->sendMoviderSms($phone, $message);
    }

    /**
     * Get SMS configuration status
     */
    public function getSmsStatus(): array
    {
        return [
            'enabled' => config('services.movider.enabled', false),
            'endpoint' => config('services.movider.endpoint'),
            'username_set' => !empty(config('services.movider.username')),
            'password_set' => !empty(config('services.movider.password')),
            'sender_id' => config('services.movider.sender_id', 'HealthApp'),
        ];
    }

    /**
     * Get broadcasting configuration status
     */
    public function getBroadcastingStatus(): array
    {
        return [
            'driver' => config('broadcasting.default'),
            'enabled' => config('broadcasting.default') !== 'null',
            'pusher_configured' => !empty(config('broadcasting.connections.pusher.key')),
        ];
    }
}