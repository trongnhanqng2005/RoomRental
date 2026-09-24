<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAnyRenter(User $user): bool
    {
        return $this->isRenter($user);
    }

    public function viewAnyLandlord(User $user): bool
    {
        return $this->isLandlord($user);
    }

    public function viewRenter(User $user, Appointment $appointment): bool
    {
        return $this->isRenter($user) && (int) $appointment->renter_id === (int) $user->id;
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->viewRenter($user, $appointment);
    }

    public function viewLandlord(User $user, Appointment $appointment): bool
    {
        if (! $this->isLandlord($user)) {
            return false;
        }

        $listing = $appointment->slot?->listing;

        return $listing !== null && (int) $listing->landlord_id === (int) $user->id;
    }

    public function accept(User $user, Appointment $appointment): bool
    {
        return $this->viewLandlord($user, $appointment);
    }

    public function reject(User $user, Appointment $appointment): bool
    {
        return $this->viewLandlord($user, $appointment);
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $this->viewLandlord($user, $appointment);
    }

    private function isRenter(User $user): bool
    {
        return $user->hasRole('RENTER');
    }

    private function isLandlord(User $user): bool
    {
        return $user->hasRole('LANDLORD');
    }
}
