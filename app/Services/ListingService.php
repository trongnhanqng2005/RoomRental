<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\ListingModeration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class ListingService
{
    public function __construct(private AppointmentService $appointmentService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Listing
    {
        $newPaths = [];

        try {
            $listing = DB::transaction(function () use ($user, $data, &$newPaths): Listing {
                $lockedUser = User::query()->lockForUpdate()->with('profile')->findOrFail($user->id);
                $this->ensurePostingPrerequisites($lockedUser);

                $listing = new Listing;
                $listing->landlord_id = $lockedUser->id;
                $listing->fill($this->listingAttributes($data));
                $listing->occupancy_status = 'AVAILABLE';
                $listing->visibility_status = 'VISIBLE';
                $listing->view_count = 0;
                $listing->expires_at = null;
                $listing->save();

                $listing->amenities()->sync(array_values($data['amenity_ids'] ?? []));
                $this->replaceFees($listing, $data['fees'] ?? []);
                $this->createImages($listing, $data['images'], $data['cover_selection'], $newPaths);

                $moderation = $listing->moderations()->create([
                    'version_no' => 1,
                    'status' => 'PENDING',
                    'submitted_at' => now(),
                ]);

                $listing->forceFill(['current_moderation_id' => $moderation->id])->save();
                $this->ensureLandlordRole($lockedUser);

                return $listing->fresh(['images', 'fees', 'amenities', 'currentModeration']);
            });
        } catch (Throwable $exception) {
            $this->deleteFilesAfterRollback($newPaths, $exception, null);

            throw $exception;
        }

        return $listing;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Listing $listing, array $data): Listing
    {
        $newPaths = [];
        $oldPaths = [];

        try {
            $updatedListing = DB::transaction(function () use ($listing, $data, &$newPaths, &$oldPaths): Listing {
                $landlordId = Listing::query()->whereKey($listing->id)->value('landlord_id');

                if ($landlordId === null) {
                    abort(404);
                }

                User::query()->lockForUpdate()->findOrFail($landlordId);
                $lockedListing = Listing::query()->lockForUpdate()->findOrFail($listing->id);

                if ($lockedListing->deleted_at !== null) {
                    throw new ConflictHttpException(__('ui.listings.lifecycle_stale'));
                }

                $existingImages = $lockedListing->images()->get();

                $coreChanged = $this->coreAttributesChanged($lockedListing, $data);
                $amenitiesChanged = $this->amenitiesChanged($lockedListing, $data);
                $feesChanged = $this->feesChanged($lockedListing, $data);
                $imagesChanged = $this->imagesChanged($existingImages, $data);

                if (! $coreChanged && ! $amenitiesChanged && ! $feesChanged && ! $imagesChanged) {
                    return $lockedListing->fresh(['images', 'fees', 'amenities', 'currentModeration']);
                }

                if ($coreChanged) {
                    $lockedListing->fill($this->listingAttributes($data));
                    $lockedListing->save();
                }

                if ($amenitiesChanged) {
                    $lockedListing->amenities()->sync(array_values($data['amenity_ids'] ?? []));
                }

                if ($feesChanged) {
                    $this->replaceFees($lockedListing, $data['fees'] ?? []);
                }

                if ($imagesChanged) {
                    $this->replaceImages($lockedListing, $existingImages, $data, $newPaths, $oldPaths);
                }

                $nextVersion = ((int) ListingModeration::query()
                    ->where('listing_id', $lockedListing->id)
                    ->lockForUpdate()
                    ->max('version_no')) + 1;

                $moderation = $lockedListing->moderations()->create([
                    'version_no' => $nextVersion,
                    'status' => 'PENDING',
                    'submitted_at' => now(),
                ]);

                $lockedListing->forceFill(['current_moderation_id' => $moderation->id])->save();

                return $lockedListing->fresh(['images', 'fees', 'amenities', 'currentModeration']);
            });
        } catch (Throwable $exception) {
            $this->deleteFilesAfterRollback($newPaths, $exception, $listing->id);

            throw $exception;
        }

        $this->deleteReplacedFilesAfterCommit($oldPaths, $listing->id);

        return $updatedListing;
    }

    public function findDuplicateAddress(Listing $listing): ?Listing
    {
        $normalizedAddress = $this->normalizeAddress($listing->street_address);

        return Listing::query()
            ->where('landlord_id', $listing->landlord_id)
            ->where('ward_id', $listing->ward_id)
            ->whereNull('deleted_at')
            ->whereKeyNot($listing->id)
            ->get()
            ->first(fn (Listing $candidate): bool => $this->normalizeAddress($candidate->street_address) === $normalizedAddress);
    }

    private function normalizeAddress(string $address): string
    {
        $trimmed = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $address) ?? trim($address);
        $collapsed = preg_replace('/[\s\p{Z}]+/u', ' ', $trimmed) ?? $trimmed;

        return Str::lower($collapsed);
    }

    public function updateOccupancy(Listing $listing, string $status, User $actor): void
    {
        $notifications = DB::transaction(function () use ($listing, $status, $actor): array {
            $lockedListing = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $wasRented = $lockedListing->occupancy_status === 'RENTED';
            $lockedListing->occupancy_status = $status;
            $lockedListing->save();

            if ($status === 'RENTED' && ! $wasRented) {
                return $this->appointmentService->autoCancelFutureForRentedListing($lockedListing, $actor);
            }

            return [];
        });

        $this->appointmentService->notifyRentedAppointments($notifications);
    }

    public function updateVisibility(Listing $listing, string $status): void
    {
        DB::transaction(function () use ($listing, $status): void {
            $lockedListing = Listing::query()->lockForUpdate()->findOrFail($listing->id);

            if ($lockedListing->visibility_status === 'SUSPENDED') {
                throw new AuthorizationException('Suspended listings cannot be shown by a landlord.');
            }

            $lockedListing->visibility_status = $status;
            $lockedListing->save();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function listingAttributes(array $data): array
    {
        return [
            'category_id' => (int) $data['category_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'monthly_rent' => $this->decimal($data['monthly_rent'], 2),
            'deposit_amount' => $this->decimal($data['deposit_amount'] ?? null, 2),
            'area_m2' => $this->decimal($data['area_m2'], 2),
            'max_occupants' => (int) $data['max_occupants'],
            'bedroom_count' => (int) $data['bedroom_count'],
            'bathroom_count' => (int) $data['bathroom_count'],
            'gender_requirement' => $data['gender_requirement'],
            'ward_id' => (int) $data['ward_id'],
            'street_address' => $data['street_address'],
            'latitude' => $this->decimal($data['latitude'] ?? null, 7),
            'longitude' => $this->decimal($data['longitude'] ?? null, 7),
        ];
    }

    private function ensurePostingPrerequisites(User $user): void
    {
        $errors = [];

        if (! $user->phone) {
            $errors['phone'] = __('validation.listing_phone_required');
        }

        if (! $user->profile?->contact_address) {
            $errors['contact_address'] = __('validation.listing_contact_address_required');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function ensureLandlordRole(User $user): void
    {
        $role = Role::query()->where('code', 'LANDLORD')->first();

        if (! $role) {
            throw new RuntimeException('The required LANDLORD role is not configured.');
        }

        if (! $user->roles()->whereKey($role->id)->exists()) {
            $user->roles()->attach($role->id, ['assigned_at' => now()]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $fees
     */
    private function replaceFees(Listing $listing, array $fees): void
    {
        $listing->fees()->delete();

        foreach ($fees as $fee) {
            $listing->fees()->create([
                'fee_type_id' => (int) $fee['fee_type_id'],
                'fee_unit_id' => (int) $fee['fee_unit_id'],
                'amount' => $this->decimal($fee['amount'], 2),
                'note' => $fee['note'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<int, UploadedFile>  $images
     * @param  array<int, string>  $newPaths
     */
    private function createImages(Listing $listing, array $images, string $selection, array &$newPaths): void
    {
        [$type, $value] = array_pad(explode(':', $selection, 2), 2, null);
        $coverIndex = $type === 'new' ? (int) $value : -1;

        foreach ($images as $index => $image) {
            $path = $image->store("listings/{$listing->id}", 'public');

            if (! is_string($path)) {
                throw new RuntimeException('Listing image storage failed.');
            }

            $newPaths[] = $path;
            $listing->images()->create([
                'image_url' => $path,
                'is_cover' => $index === $coverIndex,
                'display_order' => $index,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @param  Collection<int, ListingImage>  $existingImages
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $newPaths
     * @param  array<int, string>  $oldPaths
     */
    private function replaceImages(Listing $listing, Collection $existingImages, array $data, array &$newPaths, array &$oldPaths): void
    {
        $existingById = $existingImages->keyBy('id');
        $retainedIds = array_values(array_filter(array_map('intval', $data['existing_images'] ?? [])));
        $retainedPaths = $existingImages->whereIn('id', $retainedIds)->pluck('image_url')->all();
        $oldPaths = array_values(array_diff($existingImages->pluck('image_url')->all(), $retainedPaths));
        $newImages = $data['new_images'] ?? [];
        [$coverType, $coverValue] = array_pad(explode(':', (string) $data['cover_selection'], 2), 2, null);
        $coverValue = (int) $coverValue;

        $listing->images()->delete();

        foreach ($retainedIds as $index => $imageId) {
            $image = $existingById->get($imageId);

            if (! $image) {
                throw new RuntimeException('A retained listing image could not be found.');
            }

            $listing->images()->create([
                'image_url' => $image->image_url,
                'is_cover' => $coverType === 'existing' && $coverValue === $imageId,
                'display_order' => $index,
                'created_at' => $image->created_at ?? now(),
            ]);
        }

        foreach ($newImages as $index => $image) {
            $path = $image->store("listings/{$listing->id}", 'public');

            if (! is_string($path)) {
                throw new RuntimeException('Listing image storage failed.');
            }

            $newPaths[] = $path;
            $listing->images()->create([
                'image_url' => $path,
                'is_cover' => $coverType === 'new' && $coverValue === $index,
                'display_order' => count($retainedIds) + $index,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function coreAttributesChanged(Listing $listing, array $data): bool
    {
        foreach ($this->listingAttributes($data) as $field => $value) {
            if ($this->normalize($field, $listing->getAttribute($field)) !== $this->normalize($field, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function amenitiesChanged(Listing $listing, array $data): bool
    {
        $current = $listing->amenities()->pluck('amenities.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $incoming = collect($data['amenity_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();

        return $current !== $incoming;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function feesChanged(Listing $listing, array $data): bool
    {
        $current = $listing->fees()->get()->map(fn ($fee) => [
            'fee_type_id' => (int) $fee->fee_type_id,
            'fee_unit_id' => (int) $fee->fee_unit_id,
            'amount' => $this->decimal($fee->amount, 2),
            'note' => $fee->note,
        ])->sortBy('fee_type_id')->values()->all();
        $incoming = collect($data['fees'] ?? [])->map(fn ($fee) => [
            'fee_type_id' => (int) $fee['fee_type_id'],
            'fee_unit_id' => (int) $fee['fee_unit_id'],
            'amount' => $this->decimal($fee['amount'], 2),
            'note' => $fee['note'] ?? null,
        ])->sortBy('fee_type_id')->values()->all();

        return $current !== $incoming;
    }

    /**
     * @param  Collection<int, ListingImage>  $existingImages
     * @param  array<string, mixed>  $data
     */
    private function imagesChanged(Collection $existingImages, array $data): bool
    {
        $retainedIds = array_values(array_filter(array_map('intval', $data['existing_images'] ?? [])));
        $newImages = $data['new_images'] ?? [];
        $selection = (string) $data['cover_selection'];
        [$coverType, $coverValue] = array_pad(explode(':', $selection, 2), 2, null);
        $coverValue = (int) $coverValue;

        if ($newImages !== [] || count($retainedIds) !== $existingImages->count()) {
            return true;
        }

        foreach ($retainedIds as $index => $imageId) {
            $image = $existingImages->get($index);

            if (! $image || $image->id !== $imageId) {
                return true;
            }

            $isCover = $coverType === 'existing' && $coverValue === $imageId;

            if ((bool) $image->is_cover !== $isCover || $image->display_order !== $index) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $field, mixed $value): mixed
    {
        if (in_array($field, ['monthly_rent', 'deposit_amount', 'area_m2'], true)) {
            return $this->decimal($value, 2);
        }

        if (in_array($field, ['latitude', 'longitude'], true)) {
            return $this->decimal($value, 7);
        }

        if (in_array($field, ['category_id', 'max_occupants', 'bedroom_count', 'bathroom_count', 'ward_id'], true)) {
            return (int) $value;
        }

        return $value;
    }

    private function decimal(mixed $value, int $precision): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $precision, '.', '');
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteFilesAfterRollback(array $paths, Throwable $exception, ?int $listingId): void
    {
        foreach (array_unique($paths) as $path) {
            try {
                if (! Storage::disk('public')->delete($path)) {
                    Log::warning('Listing image cleanup after rollback did not delete a file.', [
                        'listing_id' => $listingId,
                        'path' => $path,
                    ]);
                }
            } catch (Throwable $cleanupException) {
                Log::warning('Listing image cleanup after rollback failed.', [
                    'listing_id' => $listingId,
                    'path' => $path,
                    'error' => $cleanupException->getMessage(),
                    'transaction_error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteReplacedFilesAfterCommit(array $paths, int $listingId): void
    {
        foreach (array_unique($paths) as $path) {
            try {
                if (! Storage::disk('public')->delete($path)) {
                    Log::warning('Listing image orphan cleanup after commit did not delete a file.', [
                        'listing_id' => $listingId,
                        'path' => $path,
                    ]);
                }
            } catch (Throwable $exception) {
                Log::warning('Listing image orphan cleanup after commit failed.', [
                    'listing_id' => $listingId,
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
