<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $appointment;
    protected $event;

    public function __construct(Appointment $appointment, string $event)
    {
        $this->appointment = $appointment;
        $this->event = $event;
    }

    public function handle(NotificationService $notificationService)
    {
        $notificationService->sendSmsNotification($this->appointment, $this->event);
        $notificationService->sendRealtimeNotification($this->appointment, $this->event);
    }

    public function failed(\Throwable $exception)
    {
        logger()->error('Failed to send appointment notification', [
            'appointment_id' => $this->appointment->id,
            'event' => $this->event,
            'error' => $exception->getMessage()
        ]);
    }
}