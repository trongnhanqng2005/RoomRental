<?php

namespace App\Http\Requests\Public;

use App\Models\District;
use App\Models\Ward;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListingSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'ward_id' => ['nullable', 'integer', Rule::exists('wards', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'min_area' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_area' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'amenity_ids' => ['nullable', 'array'],
            'amenity_ids.*' => ['required', 'integer', 'distinct', Rule::exists('amenities', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'gender_requirement' => ['nullable', Rule::in(['ANY', 'MALE', 'FEMALE'])],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'price_asc', 'price_desc', 'area_asc', 'area_desc', 'most_viewed'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $provinceId = $this->input('province_id');
            $districtId = $this->input('district_id');
            $wardId = $this->input('ward_id');

            if ($districtId && ! $provinceId) {
                $validator->errors()->add('province_id', __('validation.public_location_parent_required'));
            } elseif ($provinceId && $districtId && ! District::query()
                ->whereKey($districtId)
                ->where('province_id', $provinceId)
                ->where('is_active', true)
                ->exists()) {
                $validator->errors()->add('district_id', __('validation.listing_location_mismatch'));
            }

            if ($wardId && ! $districtId) {
                $validator->errors()->add('district_id', __('validation.public_location_parent_required'));
            } elseif ($districtId && $wardId && ! Ward::query()
                ->whereKey($wardId)
                ->where('district_id', $districtId)
                ->where('is_active', true)
                ->exists()) {
                $validator->errors()->add('ward_id', __('validation.listing_location_mismatch'));
            }

            foreach (['price', 'area'] as $range) {
                $minimum = $this->input('min_'.$range);
                $maximum = $this->input('max_'.$range);

                if (is_numeric($minimum) && is_numeric($maximum) && (float) $minimum > (float) $maximum) {
                    $validator->errors()->add('min_'.$range, __('validation.public_min_exceeds_max'));
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'q' => __('ui.public_listings.keyword'),
            'province_id' => __('ui.listings.province'),
            'district_id' => __('ui.listings.district'),
            'ward_id' => __('ui.listings.ward'),
            'min_price' => __('ui.public_listings.min_price'),
            'max_price' => __('ui.public_listings.max_price'),
            'min_area' => __('ui.public_listings.min_area'),
            'max_area' => __('ui.public_listings.max_area'),
            'amenity_ids' => __('ui.listings.amenities'),
            'amenity_ids.*' => __('ui.listings.amenities'),
            'gender_requirement' => __('ui.listings.gender_requirement'),
            'sort' => __('ui.public_listings.sort'),
        ];
    }
}
