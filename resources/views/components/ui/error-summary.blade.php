@props([
    'fields' => [],
    'id' => 'form-errors',
])

@php
    $invalidFields = collect($fields)->filter(fn ($label, $field) => $errors->has($field));
@endphp

@if ($invalidFields->isNotEmpty())
    <section
        class="mb-6 rounded-control border border-danger-100 bg-danger-50 p-4 text-danger-700"
        role="alert"
        tabindex="-1"
        aria-labelledby="{{ $id }}-title"
        data-error-summary
    >
        <h2 class="text-sm font-bold" id="{{ $id }}-title">
            {{ $invalidFields->count() === 1 ? __('ui.forms.check_one_field') : __('ui.forms.check_multiple_fields') }}
        </h2>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($invalidFields as $field => $label)
                <li>
                    <a class="font-medium underline decoration-danger-700/40 underline-offset-2 hover:decoration-danger-700" href="#{{ $field }}">
                        {{ $errors->first($field) }}
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
