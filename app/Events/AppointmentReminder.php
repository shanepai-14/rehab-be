<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentReminder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $appointment;
    public $eventData;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment->load(['patient', 'doctor']);
        $this->eventData = [
            'appointment_id' => $this->appointment->id,
            'event' => 'reminder',
            'message' => 'Appointment reminder: You have an appointment tomorrow',
            'appointment' => [
                'id' => $this->appointment->id,
                'agenda' => $this->appointment->agenda,
                'date' => $this->appointment->formatted_date,
                'time' => $this->appointment->formatted_time,
                'patient' => $this->appointment->patient->full_name,
                'doctor' => $this->appointment->doctor ? $this->appointment->doctor->full_name : null,
                'status' => $this->appointment->status,
                'priority' => $this->appointment->priority,
                'location' => $this->appointment->location
            ],
            'timestamp' => now()->toISOString()
        ];
    }

    public function broadcastOn()
    {
        $channels = [
            new PrivateChannel("user.{$this->appointment->patient_id}")
        ];

        if ($this->appointment->doctor_id) {
            $channels[] = new PrivateChannel("user.{$this->appointment->doctor_id}");
        }

        return $channels;
    }

    public function broadcastAs()
    {
        return 'appointment.reminder';
    }

    public function broadcastWith()
    {
        return $this->eventData;
    }
}