<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateViewingSlotStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageViewingSlots', $this->route('listing'));
    }

    /** @return array<string, array<int, string|Rule>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['OPEN', 'CLOSED'])]];
    }
}
