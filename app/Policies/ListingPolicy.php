<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('LANDLORD');
    }

    public function view(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing) && $listing->deleted_at === null;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole('RENTER', 'LANDLORD');
    }

    public function update(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing) && $listing->deleted_at === null;
    }

    public function updateOccupancy(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing) && $listing->deleted_at === null;
    }

    public function updateVisibility(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing)
            && $listing->deleted_at === null
            && $listing->visibility_status !== 'SUSPENDED';
    }

    public function renew(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    public function manageViewingSlots(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing) && $listing->deleted_at === null;
    }

    private function owns(User $user, Listing $listing): bool
    {
        return $user->hasRole('LANDLORD') && $listing->landlord_id === $user->id;
    }
}
