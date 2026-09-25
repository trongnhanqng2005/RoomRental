<?php

namespace App\Http\Requests\Listing;

use App\Models\District;
use App\Models\Listing;
use App\Models\Ward;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Listing::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function commonRules(): array
    {
        return [
            'category_id' => $this->categoryIdRules(),
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'monthly_rent' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'area_m2' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'max_occupants' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'bedroom_count' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'bathroom_count' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'gender_requirement' => ['required', Rule::in(['ANY', 'MALE', 'FEMALE'])],
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'ward_id' => ['required', 'integer', Rule::exists('wards', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'street_address' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'amenity_ids' => ['nullable', 'array'],
            'amenity_ids.*' => $this->amenityIdRules(),
            'fees' => ['nullable', 'array'],
            'fees.*.fee_type_id' => ['required', 'integer', 'distinct', Rule::exists('fee_types', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'fees.*.fee_unit_id' => ['required', 'integer', Rule::exists('fee_units', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'fees.*.amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'fees.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<int, mixed> */
    protected function categoryIdRules(): array
    {
        return ['required', 'integer', Rule::exists('room_categories', 'id')->where(fn ($query) => $query->where('is_active', true))];
    }

    /** @return array<int, mixed> */
    protected function amenityIdRules(): array
    {
        return ['integer', 'distinct', Rule::exists('amenities', 'id')->where(fn ($query) => $query->where('is_active', true))];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $provinceId = $this->input('province_id');
            $districtId = $this->input('district_id');
            $wardId = $this->input('ward_id');

            if ($provinceId && $districtId && ! District::query()
                ->whereKey($districtId)
                ->where('province_id', $provinceId)
                ->where('is_active', true)
                ->exists()) {
                $validator->errors()->add('district_id', __('validation.listing_location_mismatch'));
            }

            if ($districtId && $wardId && ! Ward::query()
                ->whereKey($wardId)
                ->where('district_id', $districtId)
                ->where('is_active', true)
                ->exists()) {
                $validator->errors()->add('ward_id', __('validation.listing_location_mismatch'));
            }

            $this->addListingValidationErrors($validator);
        });
    }

    protected function addListingValidationErrors(Validator $validator): void
    {
        // Specialized listing requests add image and cover invariants here.
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['title', 'description', 'street_address'] as $key) {
            if ($this->exists($key) && is_string($this->input($key))) {
                $data[$key] = trim($this->input($key));
            }
        }

        $fees = $this->input('fees', []);

        if (is_array($fees)) {
            foreach ($fees as $index => $fee) {
                if (is_array($fee) && isset($fee['note']) && is_string($fee['note'])) {
                    $fees[$index]['note'] = trim($fee['note']) ?: null;
                }
            }

            $data['fees'] = $fees;
        }

        $this->merge($data);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => __('ui.listings.category'),
            'title' => __('ui.listings.title'),
            'description' => __('ui.listings.description'),
            'monthly_rent' => __('ui.listings.monthly_rent'),
            'deposit_amount' => __('ui.listings.deposit_amount'),
            'area_m2' => __('ui.listings.area_m2'),
            'max_occupants' => __('ui.listings.max_occupants'),
            'bedroom_count' => __('ui.listings.bedroom_count'),
            'bathroom_count' => __('ui.listings.bathroom_count'),
            'gender_requirement' => __('ui.listings.gender_requirement'),
            'province_id' => __('ui.listings.province'),
            'district_id' => __('ui.listings.district'),
            'ward_id' => __('ui.listings.ward'),
            'street_address' => __('ui.listings.street_address'),
            'latitude' => __('ui.listings.latitude'),
            'longitude' => __('ui.listings.longitude'),
            'amenity_ids' => __('ui.listings.amenities'),
            'amenity_ids.*' => __('ui.listings.amenities'),
            'fees.*.fee_type_id' => __('ui.listings.fee_type'),
            'fees.*.fee_unit_id' => __('ui.listings.fee_unit'),
            'fees.*.amount' => __('ui.listings.fee_amount'),
            'fees.*.note' => __('ui.listings.fee_note'),
            'images' => __('ui.listings.images'),
            'images.*' => __('ui.listings.image'),
            'new_images' => __('ui.listings.images'),
            'new_images.*' => __('ui.listings.image'),
            'cover_selection' => __('ui.listings.cover_image'),
        ];
    }
}
