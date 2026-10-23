@extends('layouts.app')

@section('title', __('ui.home.title'))

@section('content')
    <section class="auth-canvas flex flex-1 items-center py-14 sm:py-20 lg:py-24">
        <div class="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(24rem,0.85fr)] lg:px-8">
            <div class="max-w-2xl">
                <p class="mb-5 inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white/80 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.14em] text-brand-700 shadow-sm">
                    <i class="size-4" data-lucide="sparkles"></i>
                    {{ __('ui.home.eyebrow') }}
                </p>
                <h1 class="text-4xl font-bold leading-[1.08] tracking-[-0.055em] text-slate-950 sm:text-5xl lg:text-6xl">{{ __('ui.home.heading') }}</h1>
                <p class="mt-6 max-w-xl text-base leading-8 text-slate-600 sm:text-lg">{{ __('ui.home.introduction') }}</p>

                @guest
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a class="inline-flex h-12 items-center justify-center gap-2 rounded-control border border-slate-300 bg-white px-5 text-sm font-bold whitespace-nowrap text-slate-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('public.listings.index') }}">
                            <i class="size-[18px] shrink-0" data-lucide="search"></i>
                            <span>{{ __('ui.home.browse_rooms') }}</span>
                        </a>
                        <a class="inline-flex h-12 items-center justify-center gap-2 rounded-control border border-transparent bg-brand-600 px-5 text-sm font-bold whitespace-nowrap text-white shadow-sm transition hover:bg-brand-700 active:scale-[0.98]" href="{{ route('register') }}">
                            <span>{{ __('ui.home.create_renter_account') }}</span>
                            <i class="size-[18px] shrink-0" data-lucide="arrow-right"></i>
                        </a>
                        <a class="inline-flex h-12 items-center justify-center rounded-control border border-slate-300 bg-white px-5 text-sm font-bold whitespace-nowrap text-slate-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('login') }}">
                            <span>{{ __('ui.actions.login') }}</span>
                        </a>
                    </div>
                @else
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a class="inline-flex h-12 items-center justify-center gap-2 rounded-control border border-transparent bg-brand-600 px-6 text-sm font-bold whitespace-nowrap text-white shadow-sm transition hover:bg-brand-700 active:scale-[0.98]" href="{{ route('public.listings.index') }}">
                            <i class="size-[18px] shrink-0" data-lucide="search"></i>
                            <span>{{ __('ui.home.browse_rooms') }}</span>
                        </a>
                        <span class="inline-flex h-12 items-center gap-2 px-2 text-sm font-semibold text-slate-600">
                            <i class="size-4 shrink-0 text-success-700" data-lucide="check"></i>
                            <span>{{ __('ui.home.logged_in') }}</span>
                        </span>
                    </div>
                @endguest
            </div>

            <div class="home-visual relative overflow-hidden rounded-stage p-5 sm:p-7 lg:rotate-[1.25deg]" aria-label="{{ __('ui.home.journey_preview') }}">
                <div class="rounded-panel bg-gradient-to-br from-brand-800 via-brand-700 to-accent-800 p-6 text-white shadow-raised sm:p-8">
                    <div class="flex items-start justify-between gap-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-100">{{ __('ui.home.next_steps') }}</p>
                            <h2 class="mt-3 max-w-sm text-2xl font-bold tracking-[-0.035em]">{{ __('ui.home.journey_heading') }}</h2>
                        </div>
                        <span class="grid size-11 shrink-0 place-items-center rounded-control bg-white/12 text-white ring-1 ring-white/20">
                            <i class="size-5" data-lucide="home"></i>
                        </span>
                    </div>

                    <div class="mt-8 grid gap-3 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                        <div class="rounded-control border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                            <i class="size-5 text-brand-100" data-lucide="search"></i>
                            <p class="mt-3 text-sm font-bold">{{ __('ui.home.discover') }}</p>
                            <p class="mt-1 text-xs leading-5 text-white/65">{{ __('ui.home.discover_description') }}</p>
                        </div>
                        <div class="rounded-control border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                            <i class="size-5 text-brand-100" data-lucide="heart"></i>
                            <p class="mt-3 text-sm font-bold">{{ __('ui.home.save') }}</p>
                            <p class="mt-1 text-xs leading-5 text-white/65">{{ __('ui.home.save_description') }}</p>
                        </div>
                        <div class="rounded-control border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                            <i class="size-5 text-brand-100" data-lucide="map-pin"></i>
                            <p class="mt-3 text-sm font-bold">{{ __('ui.home.view') }}</p>
                            <p class="mt-1 text-xs leading-5 text-white/65">{{ __('ui.home.view_description') }}</p>
                        </div>
                    </div>
                </div>

                <div class="spatial-card interactive-lift relative -mt-3 ml-5 flex items-center gap-4 p-4 sm:ml-10">
                    <span class="grid size-11 shrink-0 place-items-center rounded-control bg-brand-50 text-brand-700">
                        <i class="size-5" data-lucide="shield-check"></i>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-slate-900">{{ __('ui.home.details_heading') }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('ui.home.details_description') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
