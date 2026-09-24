@extends('layouts.app')

@section('title', __('ui.appointments.title'))

@section('content')
    <section class="flex-1 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <header class="max-w-3xl">
                <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.layout.appointments') }}</p>
                <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950 sm:text-4xl">{{ __('ui.appointments.heading') }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.appointments.description') }}</p>
            </header>

            @if (session('status'))
                <div class="mt-6 rounded-control border border-success-100 bg-success-50 p-4 text-sm font-semibold text-success-700" role="status" aria-live="polite">{{ session('status') }}</div>
            @endif

            @if ($appointments->isEmpty())
                <div class="spatial-card mt-6 p-8 text-center sm:p-12" role="status">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand-700"><i class="size-7" data-lucide="calendar-days"></i></span>
                    <h2 class="mt-5 text-xl font-bold text-slate-950">{{ __('ui.appointments.empty_heading') }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">{{ __('ui.appointments.empty_description') }}</p>
                    <a class="mt-6 inline-flex min-h-11 items-center justify-center rounded-control border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50" href="{{ route('public.listings.index') }}">{{ __('ui.appointments.browse_rooms') }}</a>
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
                                        <p class="text-xs font-bold text-brand-700">{{ $listing->category->name }}</p>
                                        <h2 class="mt-1 break-words text-lg font-bold text-slate-950">{{ $listing->title }}</h2>
                                    </div>
                                    <span class="rounded-full border border-line bg-slate-50 px-3 py-1 text-xs font-bold text-slate-700" role="status">{{ __('ui.appointments.statuses.'.$appointment->status) }}</span>
                                </div>
                                <p class="mt-3 flex items-start gap-2 text-sm font-semibold text-slate-700">
                                    <i class="mt-0.5 size-4 shrink-0 text-brand-700" data-lucide="calendar-clock"></i>
                                    <span>{{ $appointment->slot->startAtVietnam()->format('d/m/Y') }} · {{ $appointment->slot->startAtVietnam()->format('H:i') }}–{{ $appointment->slot->endAtVietnam()->format('H:i') }}</span>
                                </p>

                                <div class="mt-4 rounded-control border border-line bg-slate-50 p-3">
                                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.public_listings.landlord_contact') }}</h3>
                                    <p class="mt-1 font-bold text-slate-900">{{ $listing->landlord->profile?->full_name ?? __('ui.public_listings.landlord') }}</p>
                                    @if ($listing->landlord->phone)
                                        <p class="mt-1 break-all text-sm text-slate-700"><a class="underline decoration-brand-600/40 underline-offset-2" href="tel:{{ preg_replace('/[^0-9+]/', '', $listing->landlord->phone) }}">{{ $listing->landlord->phone }}</a></p>
                                    @endif
                                    @if ($listing->landlord->profile?->zalo_number)
                                        <p class="mt-1 break-all text-sm text-slate-700">{{ __('ui.appointments.zalo_number') }}: {{ $listing->landlord->profile->zalo_number }}</p>
                                    @endif
                                </div>

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

                                @if ($appointment->can_cancel)
                                    <form class="mt-4 flex flex-col gap-3 border-t border-line pt-4 sm:flex-row sm:items-end" method="POST" action="{{ route('appointments.cancel', $appointment) }}">
                                        @csrf
                                        <div class="min-w-0 flex-1">
                                            <label class="mb-2 block text-sm font-bold text-slate-800" for="cancellation-reason-{{ $appointment->id }}">{{ __('ui.appointments.cancellation_reason') }} <span class="font-medium text-slate-400">({{ __('ui.forms.optional') }})</span></label>
                                            <input class="field-control" id="cancellation-reason-{{ $appointment->id }}" name="cancellation_reason" type="text" maxlength="1000" value="{{ old('cancellation_reason') }}" @if ($errors->has('cancellation_reason')) aria-describedby="cancellation-reason-error-{{ $appointment->id }}" aria-invalid="true" @endif>
                                            @error('cancellation_reason')<p class="mt-2 text-sm font-medium text-danger-700" id="cancellation-reason-error-{{ $appointment->id }}">{{ $message }}</p>@enderror
                                        </div>
                                        <x-ui.button class="w-full sm:w-auto" type="submit" variant="danger"><i class="size-4" data-lucide="calendar-x-2"></i>{{ __('ui.appointments.cancel') }}</x-ui.button>
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
