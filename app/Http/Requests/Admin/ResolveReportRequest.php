<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveReportRequest extends FormRequest
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
            'action_type' => ['required', 'string', Rule::in(['WARNING', 'SUSPEND_LISTING', 'LOCK_ACCOUNT'])],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
