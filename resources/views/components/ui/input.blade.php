@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'autocomplete' => null,
    'inputmode' => null,
    'maxlength' => null,
    'hint' => null,
    'icon' => null,
    'required' => false,
    'autofocus' => false,
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

    <div class="relative">
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 grid w-11 place-items-center text-slate-400">
                <i class="size-[18px]" data-lucide="{{ $icon }}"></i>
            </span>
        @endif

        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($inputmode) inputmode="{{ $inputmode }}" @endif
            @if ($maxlength) maxlength="{{ $maxlength }}" @endif
            @if ($required) required @endif
            @if ($autofocus) autofocus @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            {{ $attributes->except('id')->class(['field-control', 'pl-11' => $icon]) }}
        >
    </div>

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
