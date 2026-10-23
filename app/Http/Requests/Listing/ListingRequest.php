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
            'title' => ['required', 'string', 'max:10000'],
            'description' => ['required', 'string', 'max:65000'],
            'monthly_rent' => ['required', 'numeric', 'min:100000', 'max:100000000'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:300000000'],
            'area_m2' => ['required', 'numeric', 'min:5', 'max:300'],
            'max_occupants' => ['required', 'integer', 'min:1', 'max:20'],
            'bedroom_count' => ['required', 'integer', 'min:0', 'max:10'],
            'bathroom_count' => ['required', 'integer', 'min:0', 'max:10'],
            'gender_requirement' => ['required', Rule::in(['ANY', 'MALE', 'FEMALE'])],
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'ward_id' => ['required', 'integer', Rule::exists('wards', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'street_address' => ['required', 'string', 'min:5', 'max:500'],
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

            $rawTitle = (string) $this->input('title');
            $plainTitle = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($rawTitle), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $titleLength = mb_strlen($plainTitle);

            if ($titleLength < 10) {
                $validator->errors()->add('title', __('validation.min.string', ['attribute' => __('ui.listings.title'), 'min' => 10]));
            } elseif ($titleLength > 200) {
                $validator->errors()->add('title', __('validation.max.string', ['attribute' => __('ui.listings.title'), 'max' => 200]));
            }

            $rawDescription = (string) $this->input('description');
            $plainDescription = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($rawDescription), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $descriptionLength = mb_strlen($plainDescription);

            if ($descriptionLength < 30) {
                $validator->errors()->add('description', __('validation.min.string', ['attribute' => __('ui.listings.description'), 'min' => 30]));
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
