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
use App\Events\SendSmsEvent;
class AppointmentCreatedForPatient implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $appointment;
    public $patient;
    public $eventData;

    public function __construct(Appointment $appointment, User $patient)
    {
        $this->appointment = $appointment->load(['doctor']);
        $this->patient = $patient;
        
        $patientName = $patient->first_name . ' ' . $patient->last_name;
        $doctorName = $this->appointment->doctor 
            ? $this->appointment->doctor->first_name . ' ' . $this->appointment->doctor->last_name 
            : 'the doctor';
        
        $message = "Hello {$patientName}, your appointment with Dr. {$doctorName} is scheduled on {$this->appointment->formatted_date} at {$this->appointment->formatted_time}.";
        
        // Create notification in database
        Notification::create([
            'user_id' => $patient->id,
            'title' => 'Appointment Created',
            'message' => $message,
            'type' => 'success',
            'related_type' => 'App\Models\Appointment',
            'related_id' => $this->appointment->id,
            'action_url' => '/appointments/' . $this->appointment->id
        ]);

         event(new SendSmsEvent($this->patient->contact_number, $message));
        
        $this->eventData = [
            'appointment_id' => $this->appointment->id,
            'patient_id' => $patient->id,
            'event' => 'created',
            'message' => $message,
            'appointment' => [
                'id' => $this->appointment->id,
                'agenda' => $this->appointment->agenda,
                'date' => $this->appointment->formatted_date,
                'time' => $this->appointment->formatted_time,
                'doctor' => $doctorName,
                'doctor_contact_number' => $this->appointment->doctor?->contact_number,
                'status' => $this->appointment->status,
                'priority' => $this->appointment->priority
            ],
            'timestamp' => now()->toISOString()
        ];
    }

    public function broadcastOn()
    {
        return new Channel("user.{$this->patient->contact_number}");
    }

    public function broadcastAs()
    {
        return 'appointment.created';
    }

    public function broadcastWith()
    {
        return $this->eventData;
    }
}