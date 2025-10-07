<?php
// app/Models/Chat.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Add these to always load with the model
    protected $with = ['sender', 'receiver'];

    /**
     * Get the sender of the message
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id')
                    ->select('id', 'first_name', 'last_name', 'contact_number', 'district');
    }

    /**
     * Get the receiver of the message
     */
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id')
                    ->select('id', 'first_name', 'last_name', 'contact_number', 'district');
    }

    /**
     * Scope for unread messages
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for messages between two users
     */
    public function scopeBetweenUsers($query, $userId1, $userId2)
    {
        return $query->where(function($q) use ($userId1, $userId2) {
            $q->where('sender_id', $userId1)->where('receiver_id', $userId2);
        })->orWhere(function($q) use ($userId1, $userId2) {
            $q->where('sender_id', $userId2)->where('receiver_id', $userId1);
        });
    }

    /**
     * Mark message as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Get sender's full name
     */
    public function getSenderNameAttribute()
    {
        return $this->sender->first_name . ' ' . $this->sender->last_name;
    }

    /**
     * Get receiver's full name
     */
    public function getReceiverNameAttribute()
    {
        return $this->receiver->first_name . ' ' . $this->receiver->last_name;
    }

    /**
     * Get sender's contact number
     */
    public function getSenderContactNumberAttribute()
    {
        return $this->sender->contact_number;
    }

    /**
     * Get receiver's contact number
     */
    public function getReceiverContactNumberAttribute()
    {
        return $this->receiver->contact_number;
    }
}