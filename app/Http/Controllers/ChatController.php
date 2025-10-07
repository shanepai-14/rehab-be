<?php
// app/Http/Controllers/ChatController.php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\User;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * Get list of users to chat with
     */
    public function getChatList(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->role === User::ROLE_PATIENT) {
                // Patients can chat with doctors in their district
                $users = User::where('role', User::ROLE_DOCTOR)
                    ->where('district', $user->district)
                    ->select('id', 'first_name', 'last_name', 'contact_number', 'district')
                    ->get();
            } elseif ($user->role === User::ROLE_DOCTOR) {
                // Doctors can chat with patients in their district
                $users = User::where('role', User::ROLE_PATIENT)
                    ->where('district', $user->district)
                    ->select('id', 'first_name', 'last_name', 'contact_number', 'district', 'patient_type')
                    ->get();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Get unread count for each user
            $usersWithUnread = $users->map(function ($chatUser) use ($user) {
                $unreadCount = Chat::where('sender_id', $chatUser->id)
                    ->where('receiver_id', $user->id)
                    ->unread()
                    ->count();
                
                $lastMessage = Chat::betweenUsers($user->id, $chatUser->id)
                    ->latest()
                    ->first();

                return [
                    'id' => $chatUser->id,
                    'name' => $chatUser->first_name . ' ' . $chatUser->last_name,
                    'first_name' => $chatUser->first_name,
                    'last_name' => $chatUser->last_name,
                    'contact_number' => $chatUser->contact_number,
                    'district' => $chatUser->district,
                    'patient_type' => $chatUser->patient_type ?? null,
                    'unread_count' => $unreadCount,
                    'last_message' => $lastMessage ? [
                        'message' => $lastMessage->message,
                        'created_at' => $lastMessage->created_at,
                        'is_mine' => $lastMessage->sender_id === $user->id
                    ] : null
                ];
            });

            // Sort by last message time
            $sortedUsers = $usersWithUnread->sortByDesc(function ($user) {
                return $user['last_message']['created_at'] ?? null;
            })->values();

            return response()->json([
                'success' => true,
                'data' => $sortedUsers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load chat list',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get messages between current user and another user
     */
    public function getMessages(Request $request, $userId)
    {
        try {
            $currentUser = $request->user();
            
            // Verify the other user exists and include contact_number
            $otherUser = User::select('id', 'first_name', 'last_name', 'contact_number', 'district')
                            ->findOrFail($userId);
            
            // Get messages between users
            $messages = Chat::betweenUsers($currentUser->id, $userId)
                ->with(['sender', 'receiver'])
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($message) use ($currentUser) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'sender_id' => $message->sender_id,
                        'receiver_id' => $message->receiver_id,
                        'sender_contact_number' => $message->sender->contact_number,
                        'receiver_contact_number' => $message->receiver->contact_number,
                        'is_mine' => $message->sender_id === $currentUser->id,
                        'is_read' => $message->is_read,
                        'created_at' => $message->created_at,
                        'formatted_time' => $message->created_at->format('g:i A'),
                        'sender_name' => $message->sender->first_name . ' ' . $message->sender->last_name
                    ];
                });

            // Mark received messages as read
            Chat::where('sender_id', $userId)
                ->where('receiver_id', $currentUser->id)
                ->unread()
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $messages,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->first_name . ' ' . $otherUser->last_name,
                        'first_name' => $otherUser->first_name,
                        'last_name' => $otherUser->last_name,
                        'contact_number' => $otherUser->contact_number,
                        'district' => $otherUser->district
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load messages',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Send a message
     */
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            // Verify receiver exists and get their contact_number
            $receiver = User::select('id', 'first_name', 'last_name', 'contact_number', 'district')
                           ->findOrFail($request->receiver_id);
            
            // Verify they are in the same district
            if ($user->district !== $receiver->district) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only message users in your district'
                ], 403);
            }

            // Create message
            $message = Chat::create([
                'sender_id' => $user->id,
                'receiver_id' => $request->receiver_id,
                'message' => $request->message
            ]);

            // Reload with relationships to ensure we have contact numbers
            $message->refresh();
            $message->load(['sender', 'receiver']);

            // Broadcast event (this will now include contact numbers from the model accessors)
            broadcast(new MessageSent($message))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => [
                    'id' => $message->id,
                    'message' => $message->message,
                    'sender_id' => $message->sender_id,
                    'receiver_id' => $message->receiver_id,
                    'sender_contact_number' => $message->sender->contact_number,
                    'receiver_contact_number' => $message->receiver->contact_number,
                    'is_mine' => true,
                    'is_read' => false,
                    'created_at' => $message->created_at,
                    'formatted_time' => $message->created_at->format('g:i A')
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request, $userId)
    {
        try {
            $currentUser = $request->user();
            
            Chat::where('sender_id', $userId)
                ->where('receiver_id', $currentUser->id)
                ->unread()
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get unread message count
     */
    public function getUnreadCount(Request $request)
    {
        try {
            $user = $request->user();
            
            $count = Chat::where('receiver_id', $user->id)
                ->unread()
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $count
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}