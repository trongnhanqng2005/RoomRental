@extends('layouts.app')

@section('title', __('ui.login.title'))

@section('content')
    <section class="auth-canvas flex flex-1 items-center py-8 sm:py-12 lg:py-16">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(30rem,1.1fr)] lg:gap-0">
                <aside class="auth-stage overflow-hidden rounded-stage p-6 text-white sm:p-8 lg:min-h-[38rem] lg:rounded-r-none lg:p-10" aria-labelledby="login-context-title">
                    <div class="flex h-full flex-col justify-between gap-8">
                        <div class="max-w-md">
                            <p class="mb-4 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-brand-100">
                                <i class="size-4" data-lucide="sparkles"></i>
                                {{ __('ui.login.journey') }}
                            </p>
                            <h2 class="text-2xl font-bold leading-tight tracking-[-0.035em] sm:text-3xl" id="login-context-title">{{ __('ui.login.context_heading') }}</h2>
                            <p class="mt-4 max-w-sm text-sm leading-7 text-teal-50/75 sm:text-base">{{ __('ui.login.context_description') }}</p>
                        </div>

                        <div class="relative mx-auto hidden w-full max-w-md px-2 pb-3 sm:block sm:px-7">
                            <div class="auth-building p-5" aria-hidden="true">
                                <div class="grid grid-cols-3 gap-3">
                                    <span class="h-12 rounded-lg bg-white/10"></span>
                                    <span class="h-12 rounded-lg bg-brand-200/20"></span>
                                    <span class="h-12 rounded-lg bg-white/10"></span>
                                </div>
                            </div>
                            <div class="auth-float-card absolute -bottom-1 left-0 flex items-center gap-3 rounded-control px-4 py-3 text-slate-800 sm:-left-1">
                                <span class="grid size-9 place-items-center rounded-lg bg-danger-50 text-danger-700">
                                    <i class="size-[18px] fill-current" data-lucide="heart"></i>
                                </span>
                                <span>
                                    <span class="block text-xs font-bold text-slate-900">{{ __('ui.login.saved_places') }}</span>
                                    <span class="block text-[11px] text-slate-500">{{ __('ui.login.saved_places_description') }}</span>
                                </span>
                            </div>
                            <div class="auth-float-card absolute -right-1 top-5 hidden items-center gap-2 rounded-control px-3 py-2 text-xs font-bold text-brand-800 sm:flex">
                                <i class="size-4" data-lucide="map-pin"></i>
                                {{ __('ui.login.search_by_location') }}
                            </div>
                        </div>

                        <p class="text-sm text-teal-50/75">{{ __('ui.login.new_to_roomrental') }} <a class="font-bold text-white underline decoration-white/35 underline-offset-4 transition hover:decoration-white" href="{{ route('register') }}">{{ __('ui.login.create_your_account') }}</a></p>
                    </div>
                </aside>

                <div class="auth-panel rounded-stage p-6 sm:p-8 lg:-ml-3 lg:p-12">
                    <div class="mx-auto max-w-lg">
                        <p class="mb-3 text-sm font-bold text-brand-700">{{ __('ui.login.welcome_back') }}</p>
                        <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950 sm:text-4xl">{{ __('ui.login.heading') }}</h1>
                        <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.login.description') }}</p>

                        <form class="mt-8" method="POST" action="{{ route('login') }}" novalidate>
                            @csrf

                            <x-ui.error-summary :fields="['identifier' => __('ui.login.identifier'), 'password' => __('ui.login.password')]" id="login-errors" />

                            <div class="space-y-5">
                                <x-ui.input
                                    name="identifier"
                                    :label="__('ui.login.identifier')"
                                    autocomplete="username"
                                    icon="user-round"
                                    :placeholder="__('ui.login.identifier_placeholder')"
                                    required
                                />

                                <x-ui.password-input
                                    name="password"
                                    :label="__('ui.login.password')"
                                    autocomplete="current-password"
                                />
                            </div>

                            <x-ui.button class="mt-7 w-full" type="submit">
                                <i class="size-[18px]" data-lucide="log-in"></i>
                                {{ __('ui.actions.login') }}
                            </x-ui.button>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
