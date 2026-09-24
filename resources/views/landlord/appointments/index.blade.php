@extends('layouts.app')

@section('title', __('ui.layout.landlord_appointments').' | RoomRental')

@section('content')
    <section class="flex-1 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <header class="max-w-3xl">
                <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.layout.landlord_listings') }}</p>
                <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950 sm:text-4xl">{{ __('ui.appointments.appointments_heading') }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.appointments.appointments_description') }}</p>
            </header>

            @if (session('status'))
                <div class="mt-6 rounded-control border border-success-100 bg-success-50 p-4 text-sm font-semibold text-success-700" role="status" aria-live="polite">{{ session('status') }}</div>
            @endif

            @if ($appointments->isEmpty())
                <div class="spatial-card mt-6 p-8 text-center sm:p-12" role="status">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand-700"><i class="size-7" data-lucide="calendar-check-2"></i></span>
                    <h2 class="mt-5 text-xl font-bold text-slate-950">{{ __('ui.appointments.no_appointments') }}</h2>
                </div>
            @else
                <div class="mt-6 space-y-4">
                    @foreach ($appointments as $appointment)
                        @php($listing = $appointment->slot->listing)
                        <article class="spatial-card grid min-w-0 gap-4 p-4 sm:p-5 md:grid-cols-[10rem_minmax(0,1fr)]">
                            <div class="overflow-hidden rounded-control border border-line bg-slate-100">
                                @if ($listing->coverImage)
                                    <img class="aspect-[4/3] w-full object-cover md:aspect-square" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($listing->coverImage->image_url) }}" alt="{{ __('ui.public_listings.cover_image_alt', ['title' => $listing->title]) }}" width="400" height="400" loading="lazy">
                                @else
                                    <div class="grid aspect-[4/3] place-items-center text-slate-400 md:aspect-square" role="img" aria-label="{{ __('ui.public_listings.no_cover') }}"><i class="size-10" data-lucide="image"></i></div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-brand-700">{{ __('ui.appointments.listing') }}</p>
                                        <h2 class="mt-1 break-words text-lg font-bold text-slate-950">{{ $listing->title }}</h2>
                                    </div>
                                    <span class="rounded-full border border-line bg-slate-50 px-3 py-1 text-xs font-bold text-slate-700" role="status">{{ __('ui.appointments.statuses.'.$appointment->status) }}</span>
                                </div>
                                <p class="mt-3 flex items-start gap-2 text-sm font-semibold text-slate-700">
                                    <i class="mt-0.5 size-4 shrink-0 text-brand-700" data-lucide="calendar-clock"></i>
                                    <span>{{ $appointment->slot->startAtVietnam()->format('d/m/Y') }} · {{ $appointment->slot->startAtVietnam()->format('H:i') }}–{{ $appointment->slot->endAtVietnam()->format('H:i') }}</span>
                                </p>

                                <section class="mt-4 rounded-control border border-line bg-slate-50 p-3" aria-labelledby="renter-{{ $appointment->id }}">
                                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500" id="renter-{{ $appointment->id }}">{{ __('ui.appointments.contact_renter') }}</h3>
                                    <p class="mt-1 font-bold text-slate-900">{{ $appointment->renter->profile?->full_name }}</p>
                                    @if ($appointment->renter->email)
                                        <p class="mt-1 break-all text-sm text-slate-700"><a class="underline decoration-brand-600/40 underline-offset-2" href="mailto:{{ $appointment->renter->email }}">{{ $appointment->renter->email }}</a></p>
                                    @endif
                                    @if ($appointment->renter->phone)
                                        <p class="mt-1 break-all text-sm text-slate-700"><a class="underline decoration-brand-600/40 underline-offset-2" href="tel:{{ preg_replace('/[^0-9+]/', '', $appointment->renter->phone) }}">{{ $appointment->renter->phone }}</a></p>
                                    @endif
                                    @if ($appointment->renter->profile?->zalo_number)
                                        <p class="mt-1 break-all text-sm text-slate-700">{{ __('ui.appointments.zalo_number') }}: {{ $appointment->renter->profile->zalo_number }}</p>
                                    @endif
                                    @if ($appointment->renter_note)
                                        <p class="mt-3 break-words border-t border-line pt-3 text-sm leading-6 text-slate-600">{{ $appointment->renter_note }}</p>
                                    @endif
                                </section>

                                @if ($appointment->landlord_response)
                                    <div class="mt-3 rounded-control border border-accent-100 bg-accent-50 p-3">
                                        <p class="text-xs font-bold uppercase tracking-wide text-accent-700">{{ __('ui.appointments.response') }}</p>
                                        <p class="mt-1 break-words text-sm leading-6 text-slate-700">{{ $appointment->landlord_response }}</p>
                                    </div>
                                @endif

                                @if ($appointment->cancellation_reason_label)
                                    <div class="mt-3 rounded-control border border-warning-100 bg-warning-50 p-3">
                                        <p class="text-xs font-bold uppercase tracking-wide text-warning-700">{{ __('ui.appointments.cancellation_details') }}</p>
                                        <p class="mt-1 break-words text-sm leading-6 text-slate-700">{{ $appointment->cancellation_reason_label }}</p>
                                    </div>
                                @endif

                                @if ($appointment->status === 'PENDING')
                                    <div class="mt-4 grid gap-3 border-t border-line pt-4 lg:grid-cols-2">
                                        <form class="space-y-3" method="POST" action="{{ route('landlord.appointments.accept', $appointment) }}">
                                            @csrf
                                            <label class="mb-2 block text-sm font-bold text-slate-800" for="accept-response-{{ $appointment->id }}">{{ __('ui.appointments.landlord_response') }} <span class="font-medium text-slate-400">({{ __('ui.forms.optional') }})</span></label>
                                            <textarea class="field-control min-h-20 resize-y" id="accept-response-{{ $appointment->id }}" name="landlord_response" maxlength="1000" @if ($errors->has('landlord_response')) aria-describedby="accept-response-error-{{ $appointment->id }}" aria-invalid="true" @endif>{{ old('landlord_response') }}</textarea>
                                            @error('landlord_response')<p class="text-sm font-medium text-danger-700" id="accept-response-error-{{ $appointment->id }}" role="alert">{{ $message }}</p>@enderror
                                            <x-ui.button class="w-full" type="submit"><i class="size-4" data-lucide="check"></i>{{ __('ui.appointments.accept') }}</x-ui.button>
                                        </form>
                                        <form class="space-y-3" method="POST" action="{{ route('landlord.appointments.reject', $appointment) }}">
                                            @csrf
                                            <label class="mb-2 block text-sm font-bold text-slate-800" for="reject-response-{{ $appointment->id }}">{{ __('ui.appointments.landlord_response') }}</label>
                                            <textarea class="field-control min-h-20 resize-y" id="reject-response-{{ $appointment->id }}" name="landlord_response" maxlength="1000" required aria-describedby="reject-response-hint-{{ $appointment->id }}@if ($errors->has('landlord_response')) reject-response-error-{{ $appointment->id }} @endif" @if ($errors->has('landlord_response')) aria-invalid="true" @endif>{{ old('landlord_response') }}</textarea>
                                            <p class="text-xs text-slate-500" id="reject-response-hint-{{ $appointment->id }}">{{ __('ui.appointments.landlord_response_hint') }}</p>
                                            @error('landlord_response')<p class="text-sm font-medium text-danger-700" id="reject-response-error-{{ $appointment->id }}" role="alert">{{ $message }}</p>@enderror
                                            <x-ui.button class="w-full" type="submit" variant="danger"><i class="size-4" data-lucide="x"></i>{{ __('ui.appointments.reject') }}</x-ui.button>
                                        </form>
                                    </div>
                                @elseif ($appointment->can_complete)
                                    <form class="mt-4 border-t border-line pt-4" method="POST" action="{{ route('landlord.appointments.complete', $appointment) }}">
                                        @csrf
                                        <x-ui.button class="w-full sm:w-auto" type="submit" variant="secondary"><i class="size-4" data-lucide="check-check"></i>{{ __('ui.appointments.complete') }}</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
                <nav class="mt-6" aria-label="{{ __('ui.appointments.pagination') }}">{{ $appointments->links() }}</nav>
            @endif
        </div>
    </section>
@endsection
