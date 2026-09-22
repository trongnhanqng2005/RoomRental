@extends('layouts.app')

@section('title', __('ui.listings.edit_title'))

@section('content')
    <section class="flex-1 py-10 sm:py-14 lg:py-16">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <header class="max-w-3xl">
                <p class="mb-3 text-sm font-bold text-brand-700">{{ __('ui.listings.eyebrow') }}</p>
                <h1 class="break-words text-3xl font-bold tracking-[-0.04em] text-slate-950 sm:text-4xl">{{ __('ui.listings.edit_heading') }}: {{ $listing->title }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.listings.edit_description') }}</p>
                <div class="mt-5 flex flex-wrap gap-2" aria-label="{{ __('ui.listings.listing') }} {{ $listing->id }}">
                    <x-ui.status-badge axis="moderation" :value="$listing->currentModeration?->status ?? 'PENDING'" />
                    <x-ui.status-badge axis="occupancy" :value="$listing->occupancy_status" />
                    <x-ui.status-badge axis="visibility" :value="$listing->visibility_status" />
                </div>
            </header>

            <div class="mt-8 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-8">
                <div class="spatial-card p-6 sm:p-8">
                    @include('landlord.listings._form')
                </div>

                <aside class="spatial-card p-5 sm:p-6 lg:sticky lg:top-24" aria-labelledby="listing-requirements-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-control bg-brand-50 text-brand-700"><i class="size-5" data-lucide="clock-3"></i></span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900" id="listing-requirements-heading">{{ __('ui.listings.update_notice') }}</h2>
                            @if ($listing->currentModeration?->status === 'REJECTED' && $listing->currentModeration->rejection_reason)
                                <p class="mt-3 text-sm leading-6 text-danger-700">{{ $listing->currentModeration->rejection_reason }}</p>
                            @endif
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
