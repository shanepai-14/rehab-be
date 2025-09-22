<?php

// ========== BROADCASTING ROUTES (routes/channels.php) ==========

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// User-specific private channel
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Admin notifications channel
Broadcast::channel('admin.notifications', function ($user) {
    return $user->role === User::ROLE_ADMIN;
});

// Doctor district channel (optional - for district-wide notifications)
Broadcast::channel('district.{district}', function ($user, $district) {
    return $user->role === User::ROLE_DOCTOR && $user->district === $district;
});