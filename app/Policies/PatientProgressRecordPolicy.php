<?php
namespace App\Policies;

use App\Models\PatientProgressRecord;
use App\Models\User;

class PatientProgressRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_DOCTOR, User::ROLE_PATIENT]);
    }

    public function view(User $user, PatientProgressRecord $record): bool
    {
        if ($user->isAdmin() || $user->isDoctor()) {
            return true;
        }
        return $user->isPatient() && $record->patient_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isDoctor() || $user->isAdmin();
    }

    public function update(User $user, PatientProgressRecord $record): bool
    {
        if ($user->isAdmin()) return true;
        return $user->isDoctor();
    }

    public function delete(User $user, PatientProgressRecord $record): bool
    {
        if ($user->isAdmin()) return true;
        return $user->isDoctor();
    }
}

