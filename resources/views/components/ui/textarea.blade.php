@props([
    'name',
    'label',
    'value' => null,
    'rows' => 5,
    'maxlength' => null,
    'hint' => null,
    'required' => false,
])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
    $describedBy = collect([
        $hint ? $id.'-hint' : null,
        $hasError ? $id.'-error' : null,
    ])->filter()->implode(' ');
@endphp

<div>
    <label class="mb-2 block text-sm font-bold text-slate-800" for="{{ $id }}">
        {{ $label }}
        @if (! $required)
            <span class="font-medium text-slate-400">({{ __('ui.forms.optional') }})</span>
        @endif
    </label>

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($maxlength) maxlength="{{ $maxlength }}" @endif
        @if ($required) required @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->except('id')->class(['field-control', 'min-h-32', 'resize-y']) }}
    >{{ old($name, $value) }}</textarea>

    @if ($hint)
        <p class="mt-2 text-sm leading-6 text-slate-500" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-2 flex items-start gap-2 text-sm font-medium text-danger-700" id="{{ $id }}-error">
            <span aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-danger-600"></span>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
