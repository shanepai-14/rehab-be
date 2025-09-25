<?php

// ========== APPOINTMENT ROUTES (routes/api.php) ==========

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\NotificationTestController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Complete Appointment System
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
});

// Protected routes - require authentication and verification
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    
    // ========== USER PROFILE ROUTES ==========
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => $request->user()->load('createdBy')
        ]);
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    // ========== APPOINTMENT ROUTES ==========
    Route::prefix('appointments')->group(function () {
        
        // Get appointments (filtered by user role)
        Route::get('/', [AppointmentController::class, 'index']);
        
        // Create appointment (doctors and admins only)
        Route::post('/', [AppointmentController::class, 'create']);
        
        // Get single appointment
        Route::get('/{appointment}', [AppointmentController::class, 'show'])
             ->middleware('can:view,appointment');
        
        // Update appointment status (all authenticated users can update status of their appointments)
        Route::patch('/{appointment}/status', [AppointmentController::class, 'updateStatus'])
             ->middleware('can:updateStatus,appointment');
        
        // Update appointment details (doctors and admins only)
        Route::put('/{appointment}', [AppointmentController::class, 'update'])
             ->middleware('can:update,appointment');
        
        // Delete appointment (admin only)
        Route::delete('/{appointment}', [AppointmentController::class, 'destroy'])
             ->middleware('role:admin');
        
        // Get accessible patients for appointment creation (doctors and admins)
        Route::get('/patients/accessible', [AppointmentController::class, 'getAccessiblePatients'])
             ->middleware('role:doctor,admin');
        
        // Get appointment statistics (for dashboards)
        Route::get('/stats/overview', [AppointmentController::class, 'getStatistics'])
             ->middleware('role:doctor,admin');
    });

    // ========== PATIENT ROUTES ==========
    Route::middleware('role:patient')->prefix('patient')->group(function () {
        Route::get('/dashboard', [PatientController::class, 'dashboard']);
        Route::get('/appointments', [PatientController::class, 'appointments']);
        Route::get('/appointments/{appointment}', [PatientController::class, 'getAppointment'])
             ->middleware('can:view,appointment');
        Route::patch('/appointments/{appointment}/cancel', [PatientController::class, 'cancelAppointment'])
             ->middleware('can:view,appointment');
    });

    // ========== DOCTOR ROUTES ==========
    Route::middleware('role:doctor')->prefix('doctor')->group(function () {
        Route::get('/dashboard', [DoctorController::class, 'dashboard']);
        Route::get('/patients', [DoctorController::class, 'getPatients']);
        Route::get('/patients/{patient}', [DoctorController::class, 'getPatientDetails'])
             ->middleware('can:access-patient,patient');
        Route::get('/appointments', [DoctorController::class, 'getAppointments']);
        Route::get('/stats/district', [DoctorController::class, 'getDistrictStats']);
        
        // Quick appointment actions for doctors
        Route::patch('/appointments/{appointment}/confirm', [DoctorController::class, 'confirmAppointment'])
             ->middleware('can:update,appointment');
        Route::patch('/appointments/{appointment}/start', [DoctorController::class, 'startAppointment'])
             ->middleware('can:update,appointment');
        Route::patch('/appointments/{appointment}/complete', [DoctorController::class, 'completeAppointment'])
             ->middleware('can:update,appointment');
    });

    // ========== ADMIN ROUTES ==========
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/statistics', [AdminController::class, 'statistics']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users/{user}/verify', [AdminController::class, 'verifyUser']);
        Route::post('/create-doctor', [AdminController::class, 'createDoctor']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
        
        // Admin appointment management
        Route::get('/appointments', [AdminController::class, 'getAllAppointments']);
        Route::get('/appointments/stats', [AdminController::class, 'getAppointmentStats']);
        Route::patch('/appointments/{appointment}/assign-doctor', [AdminController::class, 'assignDoctor']);
        
        // District and system management
        Route::get('/districts/stats', [AdminController::class, 'getDistrictStats']);
        Route::get('/system/health', [AdminController::class, 'getSystemHealth']);
    });

    // ========== NOTIFICATION TEST ROUTES (Admin only) ==========
    Route::middleware('role:admin')->prefix('test')->group(function () {
        Route::post('/sms', [NotificationTestController::class, 'testSms']);
        Route::post('/realtime', [NotificationTestController::class, 'testRealtime']);
        Route::post('/appointment-notification', [NotificationTestController::class, 'testAppointmentNotification']);
        Route::get('/notification-status', [NotificationTestController::class, 'getStatus']);
        Route::post('/reminders', [NotificationTestController::class, 'testReminders']);
        Route::get('/upcoming-appointments', [NotificationTestController::class, 'getUpcomingAppointments']);
    });
});