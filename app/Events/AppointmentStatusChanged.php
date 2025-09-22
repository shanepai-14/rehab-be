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

class AppointmentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $appointment;
    public $oldStatus;
    public $newStatus;
    public $eventData;

    public function __construct(Appointment $appointment, string $oldStatus, string $newStatus)
    {
        $this->appointment = $appointment->load(['patient', 'doctor', 'createdBy']);
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->eventData = [
            'appointment_id' => $this->appointment->id,
            'event' => 'status_changed',
            'message' => "Appointment status changed from {$oldStatus} to {$newStatus}",
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
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
        return 'appointment.status.changed';
    }

    public function broadcastWith()
    {
        return $this->eventData;
    }
}