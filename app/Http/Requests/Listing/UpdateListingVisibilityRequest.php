<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateListingVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateVisibility', $this->route('listing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['visibility_status' => ['required', Rule::in(['VISIBLE', 'HIDDEN'])]];
    }
}
