@props([
    'name',
    'label',
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'hint' => null,
])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
    $selected = old($name, $value);
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

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->except('id')->class(['field-control']) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>

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
