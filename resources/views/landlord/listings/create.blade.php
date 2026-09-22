@extends('layouts.app')

@section('title', __('ui.listings.create_title'))

@section('content')
    <section class="flex-1 py-10 sm:py-14 lg:py-16">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <header class="max-w-3xl">
                <p class="mb-3 text-sm font-bold text-brand-700">{{ __('ui.listings.eyebrow') }}</p>
                <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950 sm:text-4xl">{{ __('ui.listings.create_heading') }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.listings.create_description') }}</p>
            </header>

            <div class="mt-8 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-8">
                <div class="spatial-card p-6 sm:p-8">
                    @if (! auth()->user()->phone || ! auth()->user()->profile?->contact_address)
                        <div class="mb-7 flex items-start gap-3 rounded-control border border-warning-100 bg-warning-50 p-4 text-sm font-semibold leading-6 text-warning-700" role="note">
                            <i class="mt-0.5 size-5 shrink-0" data-lucide="circle-alert"></i>
                            <span>{{ __('ui.listings.contact_prerequisite') }} <a class="underline underline-offset-2" href="{{ route('profile.show') }}">{{ __('ui.profile.heading') }}</a>.</span>
                        </div>
                    @endif
                    @include('landlord.listings._form')
                </div>

                <aside class="spatial-card p-5 sm:p-6 lg:sticky lg:top-24" aria-labelledby="listing-requirements-heading">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-control bg-brand-50 text-brand-700"><i class="size-5" data-lucide="shield-check"></i></span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900" id="listing-requirements-heading">{{ __('ui.listings.requirements') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('ui.listings.requirements_description') }}</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
