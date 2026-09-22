<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
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
            'full_name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\+?[0-9]+$/', Rule::unique('users', 'phone')->ignore($this->user()?->id)],
            'contact_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'zalo_number' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $email = $this->exists('email') ? $this->input('email') : $this->user()?->email;
            $phone = $this->exists('phone') ? $this->input('phone') : $this->user()?->phone;

            if ($email === null && $phone === null) {
                $validator->errors()->add('email', __('validation.required_without', [
                    'attribute' => __('validation.attributes.email'),
                    'values' => __('validation.attributes.phone'),
                ]));
                $validator->errors()->add('phone', __('validation.required_without', [
                    'attribute' => __('validation.attributes.phone'),
                    'values' => __('validation.attributes.email'),
                ]));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['full_name', 'email', 'phone', 'contact_address', 'zalo_number'] as $key) {
            if ($this->exists($key)) {
                $data[$key] = $this->trimmed($key);
            }
        }

        $this->merge($data);
    }

    private function trimmed(string $key): mixed
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
