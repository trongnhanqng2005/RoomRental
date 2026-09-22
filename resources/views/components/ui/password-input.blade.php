@props([
    'name',
    'label',
    'autocomplete',
    'hint' => null,
    'required' => true,
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
    <label class="mb-2 block text-sm font-bold text-slate-800" for="{{ $id }}">{{ $label }}</label>

    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 grid w-11 place-items-center text-slate-400">
            <i class="size-[18px]" data-lucide="key-round"></i>
        </span>
        <input
            class="field-control pl-11 pr-12"
            id="{{ $id }}"
            name="{{ $name }}"
            type="password"
            autocomplete="{{ $autocomplete }}"
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            {{ $attributes->except(['id', 'class']) }}
        >
        <button
            class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-control text-slate-500 transition hover:bg-slate-100 hover:text-brand-700 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-brand-600"
            type="button"
            aria-label="{{ __('ui.forms.show_password') }}"
            aria-pressed="false"
            data-password-toggle="{{ $id }}"
            data-password-show-label="{{ __('ui.forms.show_password') }}"
            data-password-hide-label="{{ __('ui.forms.hide_password') }}"
        >
            <i class="size-5" data-lucide="eye" data-password-show></i>
            <i class="hidden size-5" data-lucide="eye-off" data-password-hide></i>
        </button>
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
