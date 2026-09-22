@props([
    'axis',
    'value',
])

@php
    $styles = match ($axis) {
        'moderation' => match ($value) {
            'APPROVED' => ['bg-success-50 text-success-700 ring-success-100', 'check'],
            'REJECTED' => ['bg-danger-50 text-danger-700 ring-danger-100', 'circle-alert'],
            default => ['bg-warning-50 text-warning-700 ring-warning-100', 'clock-3'],
        },
        'occupancy' => $value === 'AVAILABLE'
            ? ['bg-accent-50 text-accent-700 ring-accent-100', 'door-open']
            : ['bg-slate-100 text-slate-700 ring-slate-200', 'key-round'],
        default => match ($value) {
            'VISIBLE' => ['bg-brand-50 text-brand-700 ring-brand-100', 'eye'],
            'SUSPENDED' => ['bg-danger-50 text-danger-700 ring-danger-100', 'shield-alert'],
            default => ['bg-slate-100 text-slate-700 ring-slate-200', 'eye-off'],
        },
    };
@endphp

<span class="inline-flex min-h-8 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $styles[0] }}">
    <i class="size-3.5" data-lucide="{{ $styles[1] }}"></i>
    {{ __('ui.listings.statuses.'.$axis.'.'.$value) }}
</span>
