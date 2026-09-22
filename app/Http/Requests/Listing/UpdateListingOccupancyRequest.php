<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateListingOccupancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateOccupancy', $this->route('listing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['occupancy_status' => ['required', Rule::in(['AVAILABLE', 'RENTED'])]];
    }
}
