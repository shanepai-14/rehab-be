<?php
namespace App\Console\Commands;


use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';
    protected $description = 'Send appointment reminders for upcoming appointments';

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $this->info('Starting appointment reminder process...');
        
        $result = $this->notificationService->sendAppointmentReminders();
        
        $this->info("Reminder process completed:");
        $this->info("- Total appointments: {$result['total']}");
        $this->info("- Successfully sent: {$result['success']}");
        $this->info("- Failed: {$result['failed']}");
        
        return $result['failed'] === 0 ? 0 : 1;
    }
}