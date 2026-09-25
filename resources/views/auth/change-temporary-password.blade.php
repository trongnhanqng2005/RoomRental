@extends('layouts.app')

@section('title', __('ui.password_change.title'))

@section('content')
    <section class="mx-auto w-full max-w-xl px-4 py-10 sm:px-6 lg:py-16">
        <div class="rounded-panel border border-line bg-white p-6 sm:p-8">
            <p class="mb-2 text-sm font-bold text-brand-700">RoomRental</p>
            <h1 class="text-2xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.password_change.heading') }}</h1>
            <p class="mt-3 text-sm leading-7 text-slate-600">{{ __('ui.password_change.description') }}</p>

            <form class="mt-6 space-y-5" method="POST" action="{{ route('password.change.update') }}" data-submit-once>
                @csrf
                @method('PUT')
                <x-ui.error-summary :fields="['password' => __('ui.password_change.password'), 'password_confirmation' => __('ui.password_change.confirmation')]" id="temporary-password-errors" />
                <x-ui.password-input name="password" :label="__('ui.password_change.password')" :hint="__('ui.password_change.password_hint')" autocomplete="new-password" required />
                <x-ui.password-input name="password_confirmation" :label="__('ui.password_change.confirmation')" autocomplete="new-password" required />
                <x-ui.button class="w-full" type="submit">{{ __('ui.password_change.submit') }}</x-ui.button>
            </form>
        </div>
    </section>
@endsection
