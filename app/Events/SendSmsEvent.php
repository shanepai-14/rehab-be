<?php



namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendSmsEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $to;
    public $message;
    public $messageId;

    public function __construct($to, $message, $messageId = null)
    {
        $this->to = $to;
        $this->message = $message;
        $this->messageId = $messageId ?? uniqid('sms_');
    }

    public function broadcastOn()
    {
        return new Channel('sms-gateway');
    }

    public function broadcastAs()
    {
        return 'send-sms';
    }
}