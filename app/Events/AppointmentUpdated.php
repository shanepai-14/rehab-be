<?php

namespace App\Events;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $appointment;
    public $patient;
    public $event;
    public $eventData;

    public function __construct(Appointment $appointment, User $patient, string $event)
    {
        $this->appointment = $appointment->load(['doctor']);
        $this->patient = $patient;
        $this->event = $event;
        
        $patientName = $patient->first_name . ' ' . $patient->last_name;
        $message = $this->generateMessage($patientName, $event);
        $notificationType = $this->getNotificationType($event);
        
        // Create notification in database
        Notification::create([
            'user_id' => $patient->id,
            'title' => $this->getNotificationTitle($event),
            'message' => $message,
            'type' => $notificationType,
            'related_type' => 'App\Models\Appointment',
            'related_id' => $this->appointment->id,
            'action_url' => '/appointments/' . $this->appointment->id
        ]);
        
        $this->eventData = [
            'appointment_id' => $this->appointment->id,
            'patient_id' => $patient->id,
            'event' => $event,
            'message' => $message,
            'appointment' => [
                'id' => $this->appointment->id,
                'agenda' => $this->appointment->agenda,
                'date' => $this->appointment->formatted_date,
                'time' => $this->appointment->formatted_time,
                'doctor' => $this->appointment->doctor 
                    ? $this->appointment->doctor->first_name . ' ' . $this->appointment->doctor->last_name 
                    : null,
                'doctor_contact_number' => $this->appointment->doctor?->contact_number,
                'status' => $this->appointment->status,
                'priority' => $this->appointment->priority
            ],
            'timestamp' => now()->toISOString()
        ];
    }

    private function generateMessage($patientName, $event)
    {
        $doctorName = $this->appointment->doctor 
            ? $this->appointment->doctor->first_name . ' ' . $this->appointment->doctor->last_name 
            : 'the doctor';
        $date = $this->appointment->formatted_date;
        $time = $this->appointment->formatted_time;

        $messages = [
            'updated' => "Hello {$patientName}, your appointment with Dr. {$doctorName} has been updated to {$date} at {$time}.",
            'confirmed' => "Hello {$patientName}, your appointment with Dr. {$doctorName} on {$date} at {$time} has been confirmed.",
            'cancelled' => "Hello {$patientName}, your appointment with Dr. {$doctorName} scheduled for {$date} at {$time} has been cancelled.",
            'completed' => "Hello {$patientName}, your appointment with Dr. {$doctorName} has been completed. Thank you for your visit!",
            'in_progress' => "Hello {$patientName}, your appointment with Dr. {$doctorName} is now in progress.",
        ];

        return $messages[$event] ?? "Hello {$patientName}, there's an update regarding your appointment with Dr. {$doctorName}.";
    }

    private function getNotificationTitle($event)
    {
        $titles = [
            'updated' => 'Appointment Updated',
            'confirmed' => 'Appointment Confirmed',
            'cancelled' => 'Appointment Cancelled',
            'completed' => 'Appointment Completed',
            'in_progress' => 'Appointment In Progress',
        ];

        return $titles[$event] ?? 'Appointment Update';
    }

    private function getNotificationType($event)
    {
        $types = [
            'updated' => 'info',
            'confirmed' => 'success',
            'cancelled' => 'error',
            'completed' => 'success',
            'in_progress' => 'info',
        ];

        return $types[$event] ?? 'info';
    }

    public function broadcastOn()
    {
        return new Channel("user.{$this->patient->contact_number}");
    }

    public function broadcastAs()
    {
        return "appointment.{$this->event}";
    }

    public function broadcastWith()
    {
        return $this->eventData;
    }
}