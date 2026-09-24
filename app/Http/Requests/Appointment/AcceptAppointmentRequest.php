<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AcceptAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('accept', $this->route('appointment'));
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['landlord_response' => ['nullable', 'string', 'max:1000']];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('landlord_response'))) {
            $this->merge(['landlord_response' => trim($this->input('landlord_response')) ?: null]);
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['landlord_response' => __('ui.appointments.landlord_response')];
    }
}
