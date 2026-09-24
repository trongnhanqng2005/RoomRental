<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRole('RENTER');
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['renter_note' => ['nullable', 'string', 'max:1000']];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('renter_note'))) {
            $this->merge(['renter_note' => trim($this->input('renter_note')) ?: null]);
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['renter_note' => __('ui.appointments.renter_note')];
    }
}
