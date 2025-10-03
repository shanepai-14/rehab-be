<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Notification;
class AppointmentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $appointment;
    public $eventData;
    public $event;
    public $message;

    public function __construct(Appointment $appointment, string $event = 'updated')
    {
        $this->appointment = $appointment->load(['patient', 'doctor', 'createdBy']);
        $this->event = $event;
        $this->message = $this->generateMessage($event, $this->appointment->doctor->full_name);
        $this->eventData = [
            'appointment_id' => $this->appointment->id,
            'event' => $event,
            'message' => $this->message,
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

        Notification::create([
            'user_id' => $appointment->patient->id,
            'title' => 'Appointment Update',
            'message' => $this->message,
            'type' => 'info',
            'related_type' => 'App\Models\Appointment',
            'related_id' => $appointment->id,
            'action_url' => '/appointments/' . $appointment->id
        ]);
    }

    public function broadcastOn()
    {
        $channels = [
            new Channel("user.{$this->appointment->patient->contact_number}"),
            new PrivateChannel('admin.notifications')
        ];

        if ($this->appointment->doctor_id) {
            $channels[] = new Channel("user.{$this->appointment->doctor_id}");
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

    private function generateMessage(string $event, ?string $doctorName = null)
    {
        $doctorText = $doctorName ? " with Dr. {$doctorName}" : "";

        $messages = [
            'confirmed'     => "Your appointment{$doctorText} has been confirmed",
            'cancelled'     => "Your appointment{$doctorText} has been cancelled",
            'status_changed'=> "Your appointment{$doctorText} status has been updated",
            'reminder'      => "Reminder: You have an appointment{$doctorText} scheduled soon",
            'updated'       => "Your appointment{$doctorText} has been updated",
            'in_progress'   => "Your appointment{$doctorText} is now in progress",
            'completed'     => "Your appointment{$doctorText} has been completed"
        ];

        return $messages[$event] ?? "Your appointment{$doctorText} has been updated";
    }
}