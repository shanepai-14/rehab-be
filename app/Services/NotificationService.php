<?php

// ========== NOTIFICATION SERVICE ==========
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\Appointment;
use App\Events\AppointmentCreatedForPatient;
use App\Events\AppointmentCreatedForDoctor;
use App\Events\AppointmentUpdated;
use App\Events\AppointmentStatusChanged;
use App\Events\AppointmentReminderForDoctor;
use App\Events\AppointmentReminderForPatient;
use App\Events\SendSmsEvent;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send all notifications for appointment events
     */
    public function sendAppointmentNotifications(Appointment $appointment, string $event, string $oldStatus = null)
    {
        // // Send SMS notification
        // $this->sendSmsNotification($appointment, $event);

        // Send real-time notification via Laravel Events/Broadcasting
        $this->sendRealtimeNotification($appointment, $event, $oldStatus);
    }

    /**
     * Send SMS notification via Movider
     */
    public function sendSmsNotification(Appointment $appointment, string $event)
    {
        // Check if appointment has patients relationship
        if ($appointment->patients && $appointment->patients->count() > 0) {
            // Send SMS to each patient in the appointment
            foreach ($appointment->patients as $patient) {
                $message = $this->generateSmsMessage($appointment, $event, $patient);
                $recipient = $patient->contact_number;
                

                    event(new SendSmsEvent($recipient, $message));
              
            }
        } 
        // Fallback to single patient if patients relationship doesn't exist
        elseif ($appointment->patient) {
            $message = $this->generateSmsMessage($appointment, $event, $appointment->patient);
            $recipient = $appointment->patient->contact_number;
            

                event(new SendSmsEvent($recipient, $message));
       
        }
    }

    /**
     * Send real-time notification via Laravel Events/Broadcasting
     */
    // public function sendRealtimeNotification(Appointment $appointment, string $event, string $oldStatus = null)
    // {
    //     if (!config('broadcasting.default') || config('broadcasting.default') === 'null') {
    //         Log::info('Broadcasting is disabled');
    //         return;
    //     }

    //     try {
    //         switch ($event) {
    //             case 'created':
    //                 event(new AppointmentCreatedForPatient($appointment));
    //                 event(new AppointmentCreatedForDoctor($appointment));
    //                 break;
                
    //             case 'status_changed':
    //                 if ($oldStatus) {
    //                     event(new AppointmentStatusChanged($appointment, $oldStatus, $appointment->status));
    //                 } else {
    //                     event(new AppointmentUpdated($appointment, $event));
    //                 }
    //                 break;
                
    //             case 'reminder':
    //                 event(new AppointmentReminderForDoctor($appointment));
    //                 event(new AppointmentReminderForPatient($appointment));
    //                 break;
                
    //             case 'confirmed':
    //             case 'cancelled':
    //             case 'completed':
    //             case 'in_progress':
    //             case 'updated':
    //             default:
    //                 event(new AppointmentUpdated($appointment, $event));
    //                 break;
    //         }

    //         Log::info('Real-time notification sent', [
    //             'appointment_id' => $appointment->id,
    //             'event' => $event
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error('Failed to send real-time notification', [
    //             'appointment_id' => $appointment->id,
    //             'event' => $event,
    //             'error' => $e->getMessage()
    //         ]);
    //     }
    // }

    public function sendRealtimeNotification(Appointment $appointment, string $event, string $oldStatus = null)
{
    if (!config('broadcasting.default') || config('broadcasting.default') === 'null') {
        Log::info('Broadcasting is disabled');
        return;
    }

    try {
        switch ($event) {
            case 'created':
                // Send to all patients
                if ($appointment->patients && $appointment->patients->count() > 0) {
                    foreach ($appointment->patients as $patient) {
                        event(new AppointmentCreatedForPatient($appointment, $patient));
                    }
                } 
                
                // Send to doctor
                event(new AppointmentCreatedForDoctor($appointment));
                break;
            
            case 'status_changed':
                // Send to all patients
                if ($appointment->patients && $appointment->patients->count() > 0) {
                    foreach ($appointment->patients as $patient) {
                        if ($oldStatus) {
                            event(new AppointmentStatusChanged($appointment, $patient, $oldStatus, $appointment->status));
                        } else {
                            event(new AppointmentUpdated($appointment, $patient, $event));
                        }
                    }
                } elseif ($appointment->patient) {
                    if ($oldStatus) {
                        event(new AppointmentStatusChanged($appointment, $appointment->patient, $oldStatus, $appointment->status));
                    } else {
                        event(new AppointmentUpdated($appointment, $appointment->patient, $event));
                    }
                }
                break;
            
            case 'reminder':
                event(new AppointmentReminderForDoctor($appointment));
                
                // Send reminder to all patients
                if ($appointment->patients && $appointment->patients->count() > 0) {
                    foreach ($appointment->patients as $patient) {
                        event(new AppointmentReminderForPatient($appointment, $patient));
                    }
                } elseif ($appointment->patient) {
                    event(new AppointmentReminderForPatient($appointment, $appointment->patient));
                }
                break;
            
            case 'confirmed':
            case 'cancelled':
            case 'completed':
            case 'in_progress':
            case 'updated':
            default:
                // Send to all patients
                if ($appointment->patients && $appointment->patients->count() > 0) {
                    foreach ($appointment->patients as $patient) {
                        event(new AppointmentUpdated($appointment, $patient, $event));
                    }
                } elseif ($appointment->patient) {
                    event(new AppointmentUpdated($appointment, $appointment->patient, $event));
                }
                break;
        }

        Log::info('Real-time notification sent', [
            'appointment_id' => $appointment->id,
            'event' => $event,
            'patient_count' => $appointment->patients ? $appointment->patients->count() : 1,
            'patient' => $appointment->patients
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

        private function formatPhoneNumber($phoneNumber)
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If it starts with 63, it's already in the correct format for PH
        if (substr($cleaned, 0, 2) === '63') {
            return '+' . $cleaned;
        }
        
        // If it starts with 0, replace with +63
        if (substr($cleaned, 0, 1) === '0') {
            return '+63' . substr($cleaned, 1);
        }
        
        // If it starts with 9 and is 10 digits, assume it's a PH mobile number
        if (substr($cleaned, 0, 1) === '9' && strlen($cleaned) === 10) {
            return '+63' . $cleaned;
        }
        
        // If it doesn't start with +, add it
        if (substr($phoneNumber, 0, 1) !== '+') {
            return '+' . $cleaned;
        }
        
        return $phoneNumber;
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