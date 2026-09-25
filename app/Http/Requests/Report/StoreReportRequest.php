<?php

namespace App\Http\Requests\Report;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
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
            'reason_id' => ['required', 'integer', Rule::exists('report_reasons', 'id')->where('is_active', true)],
            'description' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 65535) {
                        $fail(__('validation.report_description_too_long'));
                    }
                },
            ],
        ];
    }
}
