<?php

namespace App\Http\Requests\Listing;

use App\Models\Listing;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateListingRequest extends ListingRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('listing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'existing_images' => ['nullable', 'array'],
            'existing_images.*' => ['integer', 'distinct'],
            'new_images' => ['nullable', 'array', 'max:8'],
            'new_images.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'cover_selection' => ['required', 'string', 'regex:/^(existing|new):\d+$/'],
        ]);
    }

    /** @return array<int, mixed> */
    protected function categoryIdRules(): array
    {
        $listing = $this->route('listing');

        return ['required', 'integer', Rule::exists('room_categories', 'id')->where(function ($query) use ($listing): void {
            $query->where('is_active', true);

            if ($listing instanceof Listing) {
                $query->orWhere('id', $listing->category_id);
            }
        })];
    }

    /** @return array<int, mixed> */
    protected function amenityIdRules(): array
    {
        $listing = $this->route('listing');

        return ['integer', 'distinct', Rule::exists('amenities', 'id')->where(function ($query) use ($listing): void {
            $query->where('is_active', true);

            if ($listing instanceof Listing) {
                $query->orWhereExists(function ($pivot) use ($listing): void {
                    $pivot->selectRaw('1')
                        ->from('listing_amenities')
                        ->whereColumn('listing_amenities.amenity_id', 'amenities.id')
                        ->where('listing_amenities.listing_id', $listing->id);
                });
            }
        })];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $listing = $this->route('listing');

        if (! $listing instanceof Listing || $this->exists('amenity_ids_submitted')) {
            return;
        }

        $amenityIds = $this->input('amenity_ids');

        if ($amenityIds === null) {
            $amenityIds = $listing->amenities()->pluck('amenities.id')->all();
        } elseif (is_array($amenityIds)) {
            $hiddenAmenityIds = $listing->amenities()
                ->where('amenities.is_active', false)
                ->pluck('amenities.id')
                ->all();
            $amenityIds = array_values(array_unique(array_merge($amenityIds, $hiddenAmenityIds)));
        } else {
            return;
        }

        $this->merge(['amenity_ids' => $amenityIds]);
    }

    protected function addListingValidationErrors(Validator $validator): void
    {
        $listing = $this->route('listing');

        if (! $listing instanceof Listing) {
            return;
        }

        $existingIds = array_values(array_filter(array_map('intval', $this->input('existing_images', []))));
        $availableIds = $listing->images()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $unknownIds = array_diff($existingIds, $availableIds);

        if ($unknownIds !== []) {
            $validator->errors()->add('existing_images', __('validation.listing_image_not_owned'));
        }

        $newImages = $this->file('new_images', []);
        $totalImages = count($existingIds) + count($newImages);

        if ($totalImages < 3 || $totalImages > 8) {
            $validator->errors()->add('existing_images', __('validation.listing_image_count'));
        }

        $selection = (string) $this->input('cover_selection');
        [$type, $value] = array_pad(explode(':', $selection, 2), 2, null);
        $selectedId = $type === 'existing' ? (int) $value : null;
        $selectedNewIndex = $type === 'new' ? (int) $value : null;

        $validCover = ($type === 'existing' && in_array($selectedId, $existingIds, true))
            || ($type === 'new' && $selectedNewIndex !== null && $selectedNewIndex >= 0 && $selectedNewIndex < count($newImages));

        if (! $validCover) {
            $validator->errors()->add('cover_selection', __('validation.listing_cover_required'));
        }
    }
}
