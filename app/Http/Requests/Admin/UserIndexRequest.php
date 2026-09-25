<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole('ADMIN', 'SUPER_ADMIN') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', Rule::in(['RENTER', 'LANDLORD', 'ADMIN', 'SUPER_ADMIN'])],
            'status' => ['nullable', 'string', Rule::in(['ACTIVE', 'LOCKED'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }
}
