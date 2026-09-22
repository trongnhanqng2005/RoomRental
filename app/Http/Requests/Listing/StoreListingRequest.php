<?php

namespace App\Http\Requests\Listing;

use Illuminate\Validation\Validator;

class StoreListingRequest extends ListingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'images' => ['required', 'array', 'min:3', 'max:8'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'cover_selection' => ['required', 'string', 'regex:/^new:\d+$/'],
        ]);
    }

    protected function addListingValidationErrors(Validator $validator): void
    {
        $images = $this->file('images', []);
        $selection = (string) $this->input('cover_selection');
        $index = str_starts_with($selection, 'new:') ? (int) substr($selection, 4) : -1;

        if ($images !== [] && ($index < 0 || $index >= count($images))) {
            $validator->errors()->add('cover_selection', __('validation.listing_cover_required'));
        }
    }
}
