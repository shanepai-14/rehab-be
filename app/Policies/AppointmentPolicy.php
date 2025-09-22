<?php
namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppointmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user can view the appointment
     */
    public function view(User $user, Appointment $appointment)
    {
        switch ($user->role) {
            case User::ROLE_ADMIN:
                return true;
                
            case User::ROLE_DOCTOR:
                return $appointment->doctor_id === $user->id || 
                       $appointment->patient->district === $user->district;
                       
            case User::ROLE_PATIENT:
                return $appointment->patient_id === $user->id;
                
            default:
                return false;
        }
    }

    /**
     * Determine if user can create appointments
     */
    public function create(User $user)
    {
        return in_array($user->role, [User::ROLE_DOCTOR, User::ROLE_ADMIN]);
    }

    /**
     * Determine if user can update the appointment
     */
    public function update(User $user, Appointment $appointment)
    {
        switch ($user->role) {
            case User::ROLE_ADMIN:
                return true;
                
            case User::ROLE_DOCTOR:
                return $appointment->doctor_id === $user->id || 
                       ($appointment->patient->district === $user->district && !$appointment->doctor_id);
                       
            default:
                return false;
        }
    }

    /**
     * Determine if user can update appointment status
     */
    public function updateStatus(User $user, Appointment $appointment)
    {
        return $this->view($user, $appointment);
    }

    /**
     * Determine if user can delete the appointment
     */
    public function delete(User $user, Appointment $appointment)
    {
        return $user->role === User::ROLE_ADMIN;
    }
}
