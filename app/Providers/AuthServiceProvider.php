<?php
namespace App\Providers;

use App\Models\Appointment;
use App\Models\PatientProgressRecord;
use App\Policies\AppointmentPolicy;
use App\Policies\PatientProgressRecordPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     */
    protected $policies = [
        Appointment::class => AppointmentPolicy::class,
        PatientProgressRecord::class => PatientProgressRecordPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Custom gates
        Gate::define('create-appointments', function ($user) {
            return $user->canCreateAppointments();
        });

        Gate::define('access-patient', function ($user, $patient) {
            return $user->canAccessPatient($patient);
        });
    }
}
