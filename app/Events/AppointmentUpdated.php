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

class AppointmentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $appointment;
    public $eventData;
    public $event;

    public function __construct(Appointment $appointment, string $event = 'updated')
    {
        $this->appointment = $appointment->load(['patient', 'doctor', 'createdBy']);
        $this->event = $event;
        $this->eventData = [
            'appointment_id' => $this->appointment->id,
            'event' => $event,
            'message' => $this->generateMessage($event),
            'appointment' => [
                'id' => $this->appointment->id,
                'agenda' => $this->appointment->agenda,
                'date' => $this->appointment->formatted_date,
                'time' => $this->appointment->formatted_time,
                'patient' => $this->appointment->patient->full_name,
                'doctor' => $this->appointment->doctor ? $this->appointment->doctor->full_name : null,
                'status' => $this->appointment->status,
                'priority' => $this->appointment->priority
            ],
            'timestamp' => now()->toISOString()
        ];
    }

    public function broadcastOn()
    {
        $channels = [
            new PrivateChannel("user.{$this->appointment->patient_id}"),
            new PrivateChannel('admin.notifications')
        ];

        if ($this->appointment->doctor_id) {
            $channels[] = new PrivateChannel("user.{$this->appointment->doctor_id}");
        }

        return $channels;
    }

    public function broadcastAs()
    {
        return 'appointment.updated';
    }

    public function broadcastWith()
    {
        return $this->eventData;
    }

    private function generateMessage(string $event)
    {
        $messages = [
            'confirmed' => 'Your appointment has been confirmed',
            'cancelled' => 'Your appointment has been cancelled',
            'status_changed' => 'Your appointment status has been updated',
            'reminder' => 'Appointment reminder',
            'updated' => 'Your appointment has been updated',
            'in_progress' => 'Your appointment is now in progress',
            'completed' => 'Your appointment has been completed'
        ];

        return $messages[$event] ?? 'Your appointment has been updated';
    }
}