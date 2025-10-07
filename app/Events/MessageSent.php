<?php
// app/Events/MessageSent.php

namespace App\Events;

use App\Models\Chat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Chat $message)
    {
        $this->message = $message->load(['sender', 'receiver']);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        // Broadcast to both sender and receiver channels using contact_number
        return [
            new Channel("user.{$this->message->receiver->contact_number}"),
            new Channel("user.{$this->message->sender->contact_number}")
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs()
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'sender_id' => $this->message->sender_id,
            'receiver_id' => $this->message->receiver_id,
            'sender_contact_number' => $this->message->sender->contact_number,
            'receiver_contact_number' => $this->message->receiver->contact_number,
            'message' => $this->message->message,
            'sender_name' => $this->message->sender->first_name . ' ' . $this->message->sender->last_name,
            'receiver_name' => $this->message->receiver->first_name . ' ' . $this->message->receiver->last_name,
            'is_read' => $this->message->is_read,
            'created_at' => $this->message->created_at->toISOString(),
            'timestamp' => now()->toISOString()
        ];
    }
}