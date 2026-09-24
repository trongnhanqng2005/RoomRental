@extends('layouts.app')

@section('title', __('ui.public_listings.detail_title', ['title' => $listing->title]))

@section('content')
    @php
        $address = implode(', ', array_filter([
            $listing->street_address,
            $listing->ward->name,
            $listing->ward->district->name,
            $listing->ward->district->province->name,
        ]));
    @endphp

    <section class="flex-1 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <a class="inline-flex min-h-10 items-center gap-2 rounded-control px-2 text-sm font-bold text-brand-700 transition hover:bg-brand-50 hover:text-brand-800" href="{{ route('public.listings.index') }}">
                <i class="size-4 rotate-180" data-lucide="arrow-right"></i>
                {{ __('ui.public_listings.back_to_results') }}
            </a>

            <header class="mt-4 border-b border-line pb-6">
                <p class="mb-2 text-sm font-bold text-brand-700">{{ $listing->category->name }}</p>
                <h1 class="break-words text-3xl font-bold tracking-[-0.04em] text-slate-950 sm:text-4xl">{{ $listing->title }}</h1>
                <p class="mt-3 flex items-start gap-2 text-sm leading-6 text-slate-600 sm:text-base">
                    <i class="mt-1 size-5 shrink-0 text-brand-700" data-lucide="map-pin"></i>
                    <span>{{ $address }}</span>
                </p>
                @if ($canFavorite)
                    <form class="mt-4" method="POST" action="{{ route($isFavorited ? 'favorites.destroy' : 'favorites.store', $listing) }}">
                        @csrf
                        @if ($isFavorited) @method('DELETE') @endif
                        <x-ui.button type="submit" variant="secondary" :aria-pressed="$isFavorited ? 'true' : 'false'">
                            <i class="size-4 {{ $isFavorited ? 'fill-current text-danger-700' : 'text-slate-500' }}" data-lucide="heart"></i>
                            {{ $isFavorited ? __('ui.favorites.remove') : __('ui.favorites.save') }}
                        </x-ui.button>
                    </form>
                @elseif (! auth()->check())
                    <a class="mt-4 inline-flex min-h-11 items-center justify-center gap-2 rounded-control border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('login') }}">
                        <i class="size-4" data-lucide="heart"></i>
                        {{ __('ui.favorites.login_to_save') }}
                    </a>
                @endif
            </header>

            <div class="mt-6 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <main class="min-w-0 space-y-6">
                    <section class="spatial-card overflow-hidden p-3 sm:p-5" aria-labelledby="gallery-heading">
                        <h2 class="sr-only" id="gallery-heading">{{ __('ui.public_listings.gallery') }}</h2>
                        @if ($coverImage)
                            <figure class="overflow-hidden rounded-control border border-line bg-slate-100">
                                <img class="aspect-[16/10] w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($coverImage->image_url) }}" alt="{{ __('ui.public_listings.cover_image_alt', ['title' => $listing->title]) }}" width="1200" height="750">
                                <figcaption class="px-3 py-2 text-xs font-semibold text-slate-600">{{ __('ui.public_listings.cover') }}</figcaption>
                            </figure>
                        @else
                            <div class="grid aspect-[16/10] place-items-center rounded-control border border-line bg-slate-100 text-slate-400" role="img" aria-label="{{ __('ui.public_listings.no_cover') }}"><i class="size-12" data-lucide="image"></i></div>
                        @endif
                        @if ($galleryImages->isNotEmpty())
                            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                @foreach ($galleryImages as $image)
                                    <figure class="overflow-hidden rounded-control border border-line bg-slate-100">
                                        <img class="aspect-[4/3] w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->image_url) }}" alt="{{ __('ui.public_listings.image_alt', ['title' => $listing->title, 'number' => $loop->iteration + 1]) }}" width="480" height="360" loading="lazy">
                                        <figcaption class="px-2 py-1.5 text-xs font-semibold text-slate-600">{{ __('ui.public_listings.image_number', ['number' => $loop->iteration + 1]) }}</figcaption>
                                    </figure>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="spatial-card p-4 sm:p-6" aria-labelledby="room-information-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold tracking-[-0.03em] text-slate-950" id="room-information-heading">{{ __('ui.public_listings.room_information') }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ $listing->category->name }}</p>
                            </div>
                            <p class="text-xl font-bold text-slate-950">{{ number_format((float) $listing->monthly_rent, 0, ',', '.') }} ₫ <span class="text-xs font-semibold text-slate-500">/ {{ __('ui.listings.monthly_rent') }}</span></p>
                        </div>

                        <div class="mt-5 border-t border-line pt-5">
                            <h3 class="text-sm font-bold text-slate-800">{{ __('ui.listings.description') }}</h3>
                            <p class="mt-2 whitespace-pre-line break-words text-sm leading-7 text-slate-700">{{ $listing->description }}</p>
                        </div>

                        <dl class="mt-5 grid gap-4 border-t border-line pt-5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ([
                                ['deposit_amount', $listing->deposit_amount === null ? __('ui.public_listings.not_available') : number_format((float) $listing->deposit_amount, 0, ',', '.').' ₫'],
                                ['area_m2', number_format((float) $listing->area_m2, 2, ',', '.').' m²'],
                                ['max_occupants', $listing->max_occupants],
                                ['bedroom_count', $listing->bedroom_count],
                                ['bathroom_count', $listing->bathroom_count],
                                ['gender_requirement', __('ui.listings.'.match ($listing->gender_requirement) { 'MALE' => 'male_only', 'FEMALE' => 'female_only', default => 'any_gender' })],
                            ] as [$label, $value])
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.listings.'.$label) }}</dt>
                                    <dd class="mt-1 break-words text-sm font-semibold text-slate-800">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>

                    <section class="spatial-card p-4 sm:p-6" aria-labelledby="location-heading">
                        <h2 class="text-xl font-bold tracking-[-0.03em] text-slate-950" id="location-heading">{{ __('ui.public_listings.location') }}</h2>
                        <p class="mt-3 flex items-start gap-2 text-sm leading-7 text-slate-700">
                            <i class="mt-1 size-5 shrink-0 text-brand-700" data-lucide="map-pinned"></i>
                            <span>{{ $address }}</span>
                        </p>
                    </section>

                    <div class="grid gap-6 md:grid-cols-2">
                        <section class="spatial-card p-4 sm:p-6" aria-labelledby="amenities-heading">
                            <h2 class="text-lg font-bold text-slate-950" id="amenities-heading">{{ __('ui.listings.amenities') }}</h2>
                            @if ($listing->amenities->isEmpty())
                                <p class="mt-3 text-sm text-slate-500">{{ __('ui.public_listings.not_available') }}</p>
                            @else
                                <ul class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($listing->amenities as $amenity)
                                        <li class="rounded-full border border-line bg-slate-50 px-3 py-1.5 text-sm font-semibold text-slate-700">{{ $amenity->name }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>

                        <section class="spatial-card p-4 sm:p-6" aria-labelledby="fees-heading">
                            <h2 class="text-lg font-bold text-slate-950" id="fees-heading">{{ __('ui.listings.fees') }}</h2>
                            @if ($listing->fees->isEmpty())
                                <p class="mt-3 text-sm text-slate-500">{{ __('ui.public_listings.not_available') }}</p>
                            @else
                                <ul class="mt-3 space-y-3">
                                    @foreach ($listing->fees as $fee)
                                        <li class="rounded-control border border-line bg-slate-50 p-3 text-sm">
                                            <p class="break-words font-semibold text-slate-800">{{ $fee->feeType->name }} · {{ number_format((float) $fee->amount, 0, ',', '.') }} ₫ / {{ $fee->feeUnit->name }}</p>
                                            @if ($fee->note)<p class="mt-1 break-words text-xs leading-5 text-slate-600">{{ $fee->note }}</p>@endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    </div>
                </main>

                <aside class="space-y-4 xl:sticky xl:top-24">
                    <section class="spatial-card p-4 sm:p-5" aria-labelledby="landlord-heading">
                        <h2 class="text-lg font-bold text-slate-950" id="landlord-heading">{{ __('ui.public_listings.landlord_contact') }}</h2>
                        <div class="mt-4 flex items-center gap-3">
                            <div class="relative grid size-14 shrink-0 place-items-center overflow-hidden rounded-full bg-brand-50 text-lg font-bold text-brand-800 ring-2 ring-brand-100">
                                @if ($avatarSrc)
                                    <img class="absolute inset-0 size-full object-cover" src="{{ $avatarSrc }}" alt="" width="56" height="56" data-avatar-image data-avatar-fallback="public-landlord-avatar-fallback">
                                @endif
                                <span class="{{ $avatarSrc ? 'hidden' : '' }}" id="public-landlord-avatar-fallback" aria-hidden="true">{{ $initials ?: 'RR' }}</span>
                            </div>
                            <p class="break-words font-bold text-slate-900">{{ $listing->landlord->profile?->full_name ?? __('ui.public_listings.landlord') }}</p>
                        </div>
                        <dl class="mt-4 space-y-3 border-t border-line pt-4 text-sm">
                            @if ($listing->landlord->phone)
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.phone') }}</dt>
                                    <dd class="mt-1 break-all font-semibold text-slate-800"><a class="rounded-sm underline decoration-brand-600/40 underline-offset-2 hover:text-brand-800" href="tel:{{ preg_replace('/[^0-9+]/', '', $listing->landlord->phone) }}">{{ $listing->landlord->phone }}</a></dd>
                                </div>
                            @endif
                            @if ($listing->landlord->profile?->zalo_number)
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.zalo_number') }}</dt>
                                    <dd class="mt-1 break-all font-semibold text-slate-800">{{ $listing->landlord->profile->zalo_number }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>

                    <section class="spatial-card p-4 sm:p-5" aria-labelledby="viewing-slots-heading">
                        <h2 class="text-lg font-bold text-slate-950" id="viewing-slots-heading">{{ __('ui.appointments.available_slots') }}</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('ui.appointments.booking_window_hint') }}</p>
                        @error('slot')
                            <p class="mt-3 rounded-control border border-danger-100 bg-danger-50 p-3 text-sm font-semibold text-danger-700" role="alert">{{ $message }}</p>
                        @enderror

                        @if ($bookableViewingSlots->isEmpty())
                            <p class="mt-4 rounded-control border border-line bg-slate-50 p-3 text-sm text-slate-600">{{ __('ui.appointments.no_available_slots') }}</p>
                        @else
                            <ul class="mt-4 space-y-3">
                                @foreach ($bookableViewingSlots as $slot)
                                    <li class="rounded-control border border-line bg-white p-3">
                                        <p class="font-bold text-slate-900">{{ $slot->startAtVietnam()->format('d/m/Y') }}</p>
                                        <p class="mt-1 text-sm text-slate-600">{{ $slot->startAtVietnam()->format('H:i') }}–{{ $slot->endAtVietnam()->format('H:i') }}</p>
                                        @if ($canBookAppointment)
                                            <form class="mt-3 space-y-3" method="POST" action="{{ route('appointments.store', $slot) }}">
                                                @csrf
                                                <label class="block text-sm font-semibold text-slate-800" for="renter-note-{{ $slot->id }}">{{ __('ui.appointments.renter_note') }} <span class="font-medium text-slate-400">({{ __('ui.forms.optional') }})</span></label>
                                                <textarea class="field-control min-h-20 resize-y" id="renter-note-{{ $slot->id }}" name="renter_note" maxlength="1000" aria-describedby="renter-note-hint-{{ $slot->id }}@if ($errors->has('renter_note')) renter-note-error-{{ $slot->id }} @endif" @if ($errors->has('renter_note')) aria-invalid="true" @endif>{{ old('renter_note') }}</textarea>
                                                <p class="text-xs text-slate-500" id="renter-note-hint-{{ $slot->id }}">{{ __('ui.appointments.renter_note_hint') }}</p>
                                                @error('renter_note')<p class="text-sm font-medium text-danger-700" id="renter-note-error-{{ $slot->id }}">{{ $message }}</p>@enderror
                                                <x-ui.button class="w-full" type="submit"><i class="size-4" data-lucide="calendar-plus-2"></i>{{ __('ui.appointments.book') }}</x-ui.button>
                                            </form>
                                        @elseif (! auth()->check())
                                            <a class="mt-3 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-control border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('login') }}">
                                                <i class="size-4" data-lucide="log-in"></i>
                                                {{ __('ui.appointments.login_to_book') }}
                                            </a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </aside>
            </div>
        </div>
    </section>
@endsection
