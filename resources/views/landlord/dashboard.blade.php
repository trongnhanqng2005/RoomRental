@extends('layouts.app')

@section('title', __('ui.dashboard.landlord_title'))

@section('content')
    <section class="flex-1 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <header class="max-w-3xl">
                <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.dashboard.landlord_eyebrow') }}</p>
                <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950">{{ __('ui.dashboard.landlord_heading') }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.dashboard.landlord_description') }}</p>
            </header>

            @php
                $cards = [
                    ['key' => 'total_listings', 'icon' => 'list', 'value' => $metrics['total_listings'], 'href' => route('landlord.listings.index')],
                    ['key' => 'available_rooms', 'icon' => 'door-open', 'value' => $metrics['available_rooms'], 'href' => route('landlord.listings.index', ['occupancy_status' => 'AVAILABLE'])],
                    ['key' => 'rented_rooms', 'icon' => 'building-2', 'value' => $metrics['rented_rooms'], 'href' => route('landlord.listings.index', ['occupancy_status' => 'RENTED'])],
                    ['key' => 'waiting_appointments', 'icon' => 'calendar-clock', 'value' => $metrics['waiting_appointments'], 'href' => route('landlord.appointments.index')],
                    ['key' => 'total_views', 'icon' => 'eye', 'value' => $metrics['total_views'], 'href' => route('landlord.listings.index')],
                ];
            @endphp

            <div class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ($cards as $card)
                    <a class="spatial-card flex min-h-28 items-center gap-4 p-4 transition hover:border-brand-200 hover:bg-brand-50/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 sm:p-5" href="{{ $card['href'] }}">
                        <span class="grid size-11 shrink-0 place-items-center rounded-control bg-brand-50 text-brand-700" aria-hidden="true"><i class="size-5" data-lucide="{{ $card['icon'] }}"></i></span>
                        <span class="min-w-0">
                            <span class="block text-xs font-bold uppercase tracking-[0.08em] text-slate-500">{{ __('ui.dashboard.'.$card['key']) }}</span>
                            <span class="mt-1 block text-2xl font-bold tracking-[-0.04em] text-slate-950">{{ number_format($card['value']) }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
