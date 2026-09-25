<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['ADMIN', 'SUPER_ADMIN']) ?? false;
    }

    protected function catalogRules(string $table, mixed $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique($table, 'name')->ignore($ignore)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($name = $this->input('name'))) {
            return;
        }

        $trimmed = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $name);
        $trimmed ??= trim($name);
        $normalized = preg_replace('/[\s\p{Z}]+/u', ' ', $trimmed);

        $this->merge(['name' => $normalized ?? $trimmed]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('ui.catalogs.name'),
            'description' => __('ui.catalogs.description'),
        ];
    }
}
