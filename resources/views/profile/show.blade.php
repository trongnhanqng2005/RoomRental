@extends('layouts.app')

@section('title', __('ui.profile.title'))

@section('content')
    <section class="flex-1 py-10 sm:py-14 lg:py-16">
        <div class="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
            <header class="max-w-2xl">
                <p class="mb-3 text-sm font-bold text-brand-700">{{ __('ui.profile.eyebrow') }}</p>
                <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950 sm:text-4xl">{{ __('ui.profile.heading') }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.profile.description') }}</p>
            </header>

            <div class="mt-8 grid items-start gap-6 lg:grid-cols-[minmax(15rem,0.72fr)_minmax(0,1.28fr)] lg:gap-8">
                <aside class="spatial-card p-6 sm:p-7" aria-labelledby="profile-identity-heading">
                    <div class="flex items-center gap-4">
                        <div class="relative size-20 shrink-0 overflow-hidden rounded-full bg-brand-50 text-brand-800 ring-4 ring-brand-50/80">
                            <div
                                class="grid size-full place-items-center text-xl font-bold tracking-[-0.04em] {{ $avatarSrc ? 'hidden' : '' }}"
                                id="profile-avatar-fallback"
                                aria-hidden="true"
                            >
                                {{ $initials }}
                            </div>
                            @if ($avatarSrc)
                                <img
                                    class="absolute inset-0 size-full object-cover"
                                    src="{{ $avatarSrc }}"
                                    alt=""
                                    width="80"
                                    height="80"
                                    data-avatar-image
                                    data-avatar-fallback="profile-avatar-fallback"
                                >
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-[0.14em] text-brand-700">{{ __('ui.profile.identity_label') }}</p>
                            <h2 class="mt-1 break-words text-xl font-bold tracking-[-0.03em] text-slate-950" id="profile-identity-heading">{{ $profile->full_name }}</h2>
                        </div>
                    </div>

                    <p class="mt-5 text-sm leading-6 text-slate-600">{{ __('ui.profile.avatar_read_only') }}</p>

                    <div class="mt-6 border-t border-line pt-5">
                        <h3 class="text-sm font-bold text-slate-900">{{ __('ui.profile.roles_heading') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('ui.profile.roles_description') }}</p>

                        @if ($displayRoles->isNotEmpty())
                            <ul class="mt-4 flex flex-wrap gap-2" aria-label="{{ __('ui.profile.roles_heading') }}">
                                @foreach ($displayRoles as $role)
                                    <li class="inline-flex items-center gap-2 rounded-lg border border-brand-100 bg-brand-50 px-3 py-2 text-xs font-bold text-brand-800">
                                        <i class="size-4" data-lucide="shield-check"></i>
                                        {{ $role }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-4 text-sm font-semibold text-slate-500">{{ __('ui.profile.no_role') }}</p>
                        @endif
                    </div>
                </aside>

                <div class="spatial-card p-6 sm:p-8">
                    @if (session('status'))
                        <div class="mb-7 flex items-start gap-3 rounded-control border border-success-100 bg-success-50 p-4 text-sm font-semibold leading-6 text-success-700" role="status" aria-live="polite" data-profile-status>
                            <i class="mt-0.5 size-5 shrink-0" data-lucide="check"></i>
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.update') }}" novalidate data-submit-once>
                        @csrf
                        @method('PATCH')

                        <x-ui.error-summary
                            :fields="[
                                'full_name' => __('ui.profile.full_name'),
                                'email' => __('ui.profile.email'),
                                'phone' => __('ui.profile.phone'),
                                'contact_address' => __('ui.profile.contact_address'),
                                'zalo_number' => __('ui.profile.zalo_number'),
                            ]"
                            id="profile-errors"
                        />

                        <fieldset>
                            <legend class="text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.profile.account_section') }}</legend>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('ui.profile.account_description') }}</p>

                            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                                <x-ui.input
                                    name="email"
                                    :label="__('ui.profile.email')"
                                    type="email"
                                    :value="$user->email"
                                    autocomplete="email"
                                    inputmode="email"
                                    maxlength="255"
                                    :hint="__('ui.profile.identifier_hint')"
                                    icon="mail"
                                />

                                <x-ui.input
                                    name="phone"
                                    :label="__('ui.profile.phone')"
                                    :value="$user->phone"
                                    autocomplete="tel"
                                    inputmode="tel"
                                    maxlength="20"
                                    :hint="__('ui.profile.identifier_hint')"
                                    icon="phone"
                                />
                            </div>
                        </fieldset>

                        <div class="my-8 border-t border-line"></div>

                        <fieldset>
                            <legend class="text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.profile.personal_section') }}</legend>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('ui.profile.personal_description') }}</p>

                            <div class="mt-6 space-y-5">
                                <x-ui.input
                                    name="full_name"
                                    :label="__('ui.profile.full_name')"
                                    :value="$profile->full_name"
                                    autocomplete="name"
                                    maxlength="150"
                                    icon="user-round"
                                    required
                                />

                                @php
                                    $addressHasError = $errors->has('contact_address');
                                    $addressDescribedBy = collect([
                                        'contact_address-hint',
                                        $addressHasError ? 'contact_address-error' : null,
                                    ])->filter()->implode(' ');
                                @endphp
                                <div>
                                    <label class="mb-2 block text-sm font-bold text-slate-800" for="contact_address">
                                        {{ __('ui.profile.contact_address') }}
                                        <span class="font-medium text-slate-400">({{ __('ui.forms.optional') }})</span>
                                    </label>
                                    <textarea
                                        class="field-control min-h-28 resize-y"
                                        id="contact_address"
                                        name="contact_address"
                                        rows="4"
                                        maxlength="500"
                                        autocomplete="street-address"
                                        aria-describedby="{{ $addressDescribedBy }}"
                                        @if ($addressHasError) aria-invalid="true" @endif
                                    >{{ old('contact_address', $profile->contact_address) }}</textarea>
                                    <p class="mt-2 text-sm leading-6 text-slate-500" id="contact_address-hint">{{ __('ui.profile.contact_address_hint') }}</p>
                                    @error('contact_address')
                                        <p class="mt-2 flex items-start gap-2 text-sm font-medium text-danger-700" id="contact_address-error">
                                            <span aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-danger-600"></span>
                                            <span>{{ $message }}</span>
                                        </p>
                                    @enderror
                                </div>

                                <x-ui.input
                                    name="zalo_number"
                                    :label="__('ui.profile.zalo_number')"
                                    :value="$profile->zalo_number"
                                    inputmode="tel"
                                    maxlength="20"
                                    :hint="__('ui.profile.zalo_hint')"
                                    icon="message-circle"
                                />
                            </div>
                        </fieldset>

                        <div class="mt-8 flex flex-col gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
                            <p class="max-w-md text-sm leading-6 text-slate-500">{{ __('ui.profile.save_hint') }}</p>
                            <x-ui.button class="w-full shrink-0 sm:w-auto sm:min-w-48" type="submit" :data-loading-text="__('ui.profile.saving')">
                                <i class="size-[18px]" data-lucide="check"></i>
                                <span data-submit-label>{{ __('ui.profile.save') }}</span>
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
