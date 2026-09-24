@extends('layouts.app')

@section('title', __('ui.appointments.manage_heading').' | RoomRental')

@section('content')
    <section class="flex-1 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <a class="inline-flex min-h-10 items-center gap-2 rounded-control px-2 text-sm font-bold text-brand-700 transition hover:bg-brand-50 hover:text-brand-800" href="{{ route('landlord.listings.index') }}">
                <i class="size-4 rotate-180" data-lucide="arrow-right"></i>
                {{ __('ui.listings.back_to_listings') }}
            </a>

            <header class="mt-4 max-w-3xl">
                <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.layout.landlord_listings') }}</p>
                <h1 class="break-words text-3xl font-bold tracking-[-0.04em] text-slate-950 sm:text-4xl">{{ __('ui.appointments.manage_heading') }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.appointments.manage_description') }}</p>
                <p class="mt-4 break-words text-lg font-bold text-slate-900">{{ $listing->title }}</p>
            </header>

            @if (session('status'))
                <div class="mt-6 rounded-control border border-success-100 bg-success-50 p-4 text-sm font-semibold text-success-700" role="status" aria-live="polite">{{ session('status') }}</div>
            @endif

            <x-ui.error-summary :fields="[
                'viewing_date' => __('ui.appointments.viewing_date'),
                'start_time' => __('ui.appointments.start_time'),
                'end_time' => __('ui.appointments.end_time'),
                'status' => __('ui.appointments.slot_status'),
            ]" />
            @error('status')
                <p class="mb-4 rounded-control border border-danger-100 bg-danger-50 p-3 text-sm font-semibold text-danger-700" id="status" role="alert">{{ $message }}</p>
            @enderror

            <form class="spatial-card mt-6 grid gap-4 p-4 sm:grid-cols-2 sm:p-6 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end" method="POST" action="{{ route('landlord.viewing-slots.store', $listing) }}">
                @csrf
                <x-ui.input name="viewing_date" :label="__('ui.appointments.viewing_date')" type="date" :value="old('viewing_date')" :required="true" />
                <x-ui.input name="start_time" :label="__('ui.appointments.start_time')" type="time" :value="old('start_time')" :required="true" />
                <x-ui.input name="end_time" :label="__('ui.appointments.end_time')" type="time" :value="old('end_time')" :required="true" />
                <x-ui.button class="w-full lg:w-auto" type="submit"><i class="size-4" data-lucide="plus"></i>{{ __('ui.appointments.create_slot') }}</x-ui.button>
            </form>

            @if ($slots->isEmpty())
                <div class="spatial-card mt-6 p-8 text-center sm:p-12" role="status">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand-700"><i class="size-7" data-lucide="calendar-days"></i></span>
                    <h2 class="mt-5 text-xl font-bold text-slate-950">{{ __('ui.appointments.no_slots') }}</h2>
                </div>
            @else
                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    @foreach ($slots as $slot)
                        <article class="spatial-card min-w-0 p-4 sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 class="text-lg font-bold text-slate-950">{{ $slot->startAtVietnam()->format('d/m/Y') }}</h2>
                                    <p class="mt-1 text-sm font-semibold text-slate-600">{{ $slot->startAtVietnam()->format('H:i') }}–{{ $slot->endAtVietnam()->format('H:i') }}</p>
                                </div>
                                <span class="rounded-full border px-3 py-1 text-xs font-bold {{ $slot->status === 'OPEN' ? 'border-success-100 bg-success-50 text-success-700' : 'border-slate-200 bg-slate-100 text-slate-700' }}" role="status">
                                    {{ __('ui.appointments.slot_statuses.'.$slot->status) }}
                                </span>
                            </div>

                            <div class="mt-4 border-t border-line pt-4">
                                <h3 class="text-sm font-bold text-slate-800">{{ __('ui.appointments.active_appointment') }}</h3>
                                @if ($slot->appointments->isEmpty())
                                    <p class="mt-2 text-sm text-slate-500">{{ __('ui.appointments.no_active_appointment') }}</p>
                                @else
                                    @php($appointment = $slot->appointments->first())
                                    <div class="mt-2 rounded-control border border-line bg-slate-50 p-3">
                                        <p class="font-bold text-slate-900">{{ $appointment->renter->profile?->full_name }}</p>
                                        <p class="mt-1 text-xs font-semibold text-brand-700">{{ __('ui.appointments.statuses.'.$appointment->status) }}</p>
                                        @if ($appointment->renter->email)
                                            <p class="mt-2 break-all text-sm text-slate-700"><a class="underline decoration-brand-600/40 underline-offset-2" href="mailto:{{ $appointment->renter->email }}">{{ $appointment->renter->email }}</a></p>
                                        @endif
                                        @if ($appointment->renter->phone)
                                            <p class="mt-1 break-all text-sm text-slate-700"><a class="underline decoration-brand-600/40 underline-offset-2" href="tel:{{ preg_replace('/[^0-9+]/', '', $appointment->renter->phone) }}">{{ $appointment->renter->phone }}</a></p>
                                        @endif
                                        @if ($appointment->renter->profile?->zalo_number)
                                            <p class="mt-1 break-all text-sm text-slate-700">{{ __('ui.appointments.zalo_number') }}: {{ $appointment->renter->profile->zalo_number }}</p>
                                        @endif
                                        @if ($appointment->renter_note)
                                            <p class="mt-3 break-words text-sm leading-6 text-slate-600">{{ $appointment->renter_note }}</p>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            @if ($slot->can_change_status)
                                <form class="mt-4 border-t border-line pt-4" method="POST" action="{{ route('landlord.viewing-slots.status', [$listing, $slot]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $slot->status === 'OPEN' ? 'CLOSED' : 'OPEN' }}">
                                    <x-ui.button class="w-full sm:w-auto" type="submit" variant="secondary">
                                        <i class="size-4" data-lucide="{{ $slot->status === 'OPEN' ? 'calendar-x-2' : 'calendar-check-2' }}"></i>
                                        {{ __('ui.appointments.'.($slot->status === 'OPEN' ? 'close_slot' : 'open_slot')) }}
                                    </x-ui.button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
                <nav class="mt-6" aria-label="{{ __('ui.appointments.pagination') }}">{{ $slots->links() }}</nav>
            @endif
        </div>
    </section>
@endsection
