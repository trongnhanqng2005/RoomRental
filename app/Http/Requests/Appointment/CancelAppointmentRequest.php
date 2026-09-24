<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('cancel', $this->route('appointment'));
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['cancellation_reason' => ['nullable', 'string', 'max:1000']];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cancellation_reason'))) {
            $this->merge(['cancellation_reason' => trim($this->input('cancellation_reason')) ?: null]);
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['cancellation_reason' => __('ui.appointments.cancellation_reason')];
    }
}
