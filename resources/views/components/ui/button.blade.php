@props([
    'type' => 'button',
    'variant' => 'primary',
])

@php
    $classes = match ($variant) {
        'danger' => 'border border-transparent bg-danger-700 text-white shadow-sm hover:bg-danger-700/90 active:scale-[0.98] active:bg-danger-700',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 shadow-sm hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800 active:bg-brand-100',
        'quiet' => 'border border-transparent bg-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200',
        default => 'border border-transparent bg-brand-600 text-white shadow-[0_10px_24px_-12px_rgb(15_118_110_/_0.9)] hover:bg-brand-700 hover:shadow-[0_14px_28px_-14px_rgb(15_118_110_/_0.9)] active:scale-[0.98] active:bg-brand-800',
    };
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->class([
        'inline-flex min-h-11 items-center justify-center gap-2 rounded-control px-4 py-2.5 text-sm font-bold transition duration-150 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:pointer-events-none disabled:opacity-55',
        $classes,
    ]) }}
>
    {{ $slot }}
</button>
