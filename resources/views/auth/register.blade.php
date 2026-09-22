@extends('layouts.app')

@section('title', __('ui.register.title'))

@section('content')
    <section class="auth-canvas flex flex-1 items-center py-8 sm:py-12 lg:py-16">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(36rem,1.2fr)] lg:gap-0">
                <aside class="auth-stage overflow-hidden rounded-stage p-6 text-white sm:p-8 lg:min-h-[46rem] lg:rounded-r-none lg:p-10" aria-labelledby="register-context-title">
                    <div class="flex h-full flex-col justify-between gap-8">
                        <div class="max-w-md">
                            <p class="mb-4 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-brand-100">
                                <i class="size-4" data-lucide="sparkles"></i>
                                {{ __('ui.register.eyebrow') }}
                            </p>
                            <h2 class="text-2xl font-bold leading-tight tracking-[-0.035em] sm:text-3xl" id="register-context-title">{{ __('ui.register.context_heading') }}</h2>
                            <p class="mt-4 max-w-sm text-sm leading-7 text-teal-50/75 sm:text-base">{{ __('ui.register.context_description') }}</p>
                        </div>

                        <div class="relative mx-auto hidden w-full max-w-md px-2 pb-5 sm:block sm:px-8">
                            <div class="auth-building p-5" aria-hidden="true">
                                <div class="grid grid-cols-3 gap-3">
                                    <span class="h-12 rounded-lg bg-white/10"></span>
                                    <span class="h-12 rounded-lg bg-brand-200/20"></span>
                                    <span class="h-12 rounded-lg bg-white/10"></span>
                                </div>
                            </div>
                            <div class="auth-float-card absolute -bottom-1 left-0 flex items-center gap-3 rounded-control px-4 py-3 text-slate-800">
                                <span class="grid size-9 place-items-center rounded-lg bg-brand-50 text-brand-700">
                                    <i class="size-[18px]" data-lucide="check"></i>
                                </span>
                                <span>
                                    <span class="block text-xs font-bold text-slate-900">{{ __('ui.register.account_heading') }}</span>
                                    <span class="block text-[11px] text-slate-500">{{ __('ui.register.account_description') }}</span>
                                </span>
                            </div>
                            <div class="auth-float-card absolute -right-1 top-5 hidden items-center gap-2 rounded-control px-3 py-2 text-xs font-bold text-accent-800 sm:flex">
                                <i class="size-4" data-lucide="shield-check"></i>
                                {{ __('ui.register.organized_details') }}
                            </div>
                        </div>

                        <p class="text-sm text-teal-50/75">{{ __('ui.register.already_registered') }} <a class="font-bold text-white underline decoration-white/35 underline-offset-4 transition hover:decoration-white" href="{{ route('login') }}">{{ __('ui.register.login_instead') }}</a></p>
                    </div>
                </aside>

                <div class="auth-panel rounded-stage p-6 sm:p-8 lg:-ml-3 lg:p-12">
                    <div class="mx-auto max-w-2xl">
                        <p class="mb-3 text-sm font-bold text-brand-700">{{ __('ui.register.create_renter_account') }}</p>
                        <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950 sm:text-4xl">{{ __('ui.register.heading') }}</h1>
                        <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.register.description') }}</p>

                        <form class="mt-8" method="POST" action="{{ route('register') }}" novalidate>
                            @csrf

                            <x-ui.error-summary
                                :fields="[
                                    'full_name' => __('ui.register.full_name'),
                                    'email' => __('ui.register.email'),
                                    'phone' => __('ui.register.phone'),
                                    'password' => __('ui.register.password'),
                                ]"
                                id="register-errors"
                            />

                            <div class="space-y-5">
                                <x-ui.input
                                    name="full_name"
                                    :label="__('ui.register.full_name')"
                                    autocomplete="name"
                                    icon="user-round"
                                    :placeholder="__('ui.register.full_name_placeholder')"
                                    required
                                />

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <x-ui.input
                                        name="email"
                                        :label="__('ui.register.email')"
                                        type="email"
                                        autocomplete="email"
                                        inputmode="email"
                                        placeholder="you@example.com"
                                        :hint="__('ui.register.contact_hint')"
                                    />

                                    <x-ui.input
                                        name="phone"
                                        :label="__('ui.register.phone')"
                                        autocomplete="tel"
                                        inputmode="tel"
                                        maxlength="20"
                                        placeholder="0901234567"
                                        :hint="__('ui.register.contact_options_hint')"
                                    />
                                </div>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <x-ui.password-input
                                        name="password"
                                        :label="__('ui.register.password')"
                                        autocomplete="new-password"
                                        :hint="__('ui.register.password_hint')"
                                    />

                                    <x-ui.password-input
                                        name="password_confirmation"
                                        :label="__('ui.register.password_confirmation')"
                                        autocomplete="new-password"
                                    />
                                </div>
                            </div>

                            <x-ui.button class="mt-7 w-full sm:w-auto sm:min-w-52" type="submit">
                                <i class="size-[18px]" data-lucide="user-plus"></i>
                                {{ __('ui.actions.create_account') }}
                            </x-ui.button>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
