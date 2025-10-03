<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CleanupNotifications extends Command
{
    protected $signature = 'notifications:cleanup';
    protected $description = 'Delete old notifications based on rules';

    public function handle()
    {
        // Delete unread notifications older than 1 week
        $unreadDeleted = Notification::where('is_read', false)
            ->where('created_at', '<', Carbon::now()->subWeek())
            ->delete();

        // Delete read notifications older than 1 day after being read
        $readDeleted = Notification::where('is_read', true)
            ->whereNotNull('read_at')
            ->where('read_at', '<', Carbon::now()->subDay())
            ->delete();

        $this->info("Cleanup completed:");
        $this->info("- Unread notifications deleted: {$unreadDeleted}");
        $this->info("- Read notifications deleted: {$readDeleted}");

        return 0;
    }
}