@extends('layouts.app')

@section('title', __('ui.admin_moderation.detail_title'))

@section('content')
    @php
        $moderation = $listing->currentModeration;
        $address = implode(', ', array_filter([
            $listing->street_address,
            $listing->ward->name,
            $listing->ward->district->name,
            $listing->ward->district->province->name,
        ]));
    @endphp

    <section class="flex-1 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <a class="inline-flex min-h-10 items-center gap-2 rounded-control px-2 text-sm font-bold text-brand-700 transition hover:bg-brand-50 hover:text-brand-800" href="{{ route('admin.listing-moderations.index') }}">
                <i class="size-4 rotate-180" data-lucide="arrow-right" aria-hidden="true"></i>
                {{ __('ui.admin_moderation.back_to_queue') }}
            </a>

            <header class="mt-4 flex flex-col gap-4 border-b border-line pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.admin_moderation.eyebrow') }} · {{ __('ui.admin_moderation.version') }} {{ $moderation->version_no }}</p>
                    <h1 class="break-words text-3xl font-bold tracking-[-0.04em] text-slate-950">{{ $listing->title }}</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $address }}</p>
                </div>
                <div class="flex flex-wrap gap-2" aria-label="{{ __('ui.admin_moderation.detail_heading') }}">
                    <div><span class="mb-1 block text-xs font-bold text-slate-500">{{ __('ui.listings.moderation_short') }}</span><x-ui.status-badge axis="moderation" :value="$moderation->status" /></div>
                    <div><span class="mb-1 block text-xs font-bold text-slate-500">{{ __('ui.listings.occupancy_short') }}</span><x-ui.status-badge axis="occupancy" :value="$listing->occupancy_status" /></div>
                    <div><span class="mb-1 block text-xs font-bold text-slate-500">{{ __('ui.listings.visibility_short') }}</span><x-ui.status-badge axis="visibility" :value="$listing->visibility_status" /></div>
                </div>
            </header>

            @if (session('status'))
                <div class="mt-6 flex items-start gap-3 rounded-control border border-success-100 bg-success-50 p-4 text-sm font-semibold leading-6 text-success-700" role="status" aria-live="polite">
                    <i class="mt-0.5 size-5 shrink-0" data-lucide="check"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="min-w-0 space-y-6">
                    <section class="spatial-card p-4 sm:p-6" aria-labelledby="listing-content-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold tracking-[-0.03em] text-slate-950" id="listing-content-heading">{{ __('ui.admin_moderation.listing_content') }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ $listing->category->name }}</p>
                            </div>
                            <p class="text-lg font-bold text-slate-950">{{ number_format((float) $listing->monthly_rent, 0, ',', '.') }} ₫ <span class="text-xs font-semibold text-slate-500">/ {{ __('ui.listings.monthly_rent') }}</span></p>
                        </div>

                        <div class="mt-5 border-t border-line pt-5">
                            <h3 class="text-sm font-bold text-slate-800">{{ __('ui.listings.description') }}</h3>
                            <p class="mt-2 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $listing->description }}</p>
                        </div>

                        <dl class="mt-5 grid gap-4 border-t border-line pt-5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ([
                                ['deposit_amount', $listing->deposit_amount === null ? __('ui.admin_moderation.not_available') : number_format((float) $listing->deposit_amount, 0, ',', '.').' ₫'],
                                ['area_m2', number_format((float) $listing->area_m2, 2, ',', '.').' m²'],
                                ['max_occupants', $listing->max_occupants],
                                ['bedroom_count', $listing->bedroom_count],
                                ['bathroom_count', $listing->bathroom_count],
                                ['gender_requirement', __('ui.listings.'.match ($listing->gender_requirement) { 'MALE' => 'male_only', 'FEMALE' => 'female_only', default => 'any_gender' })],
                            ] as [$label, $value])
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.listings.'.$label) }}</dt>
                                    <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <div class="mt-5 border-t border-line pt-5">
                            <h3 class="text-sm font-bold text-slate-800">{{ __('ui.admin_moderation.full_address') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ $address }}</p>
                            @if ($listing->latitude !== null && $listing->longitude !== null)
                                <p class="mt-1 text-xs text-slate-500">{{ $listing->latitude }}, {{ $listing->longitude }}</p>
                            @endif
                        </div>

                        <div class="mt-5 grid gap-5 border-t border-line pt-5 md:grid-cols-2">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">{{ __('ui.listings.amenities') }}</h3>
                                @if ($listing->amenities->isEmpty())
                                    <p class="mt-2 text-sm text-slate-500">{{ __('ui.admin_moderation.not_available') }}</p>
                                @else
                                    <ul class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($listing->amenities as $amenity)
                                            <li class="rounded-full border border-line bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">{{ $amenity->name }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">{{ __('ui.listings.fees') }}</h3>
                                @if ($listing->fees->isEmpty())
                                    <p class="mt-2 text-sm text-slate-500">{{ __('ui.admin_moderation.not_available') }}</p>
                                @else
                                    <ul class="mt-2 space-y-3">
                                        @foreach ($listing->fees as $fee)
                                            <li class="rounded-control border border-line bg-slate-50 p-3 text-sm">
                                                <p class="font-semibold text-slate-800">{{ $fee->feeType->name }} · {{ number_format((float) $fee->amount, 0, ',', '.') }} ₫ / {{ $fee->feeUnit->name }}</p>
                                                @if ($fee->note)<p class="mt-1 text-xs leading-5 text-slate-600">{{ $fee->note }}</p>@endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </section>

                    <section class="spatial-card p-4 sm:p-6" aria-labelledby="images-heading">
                        <h2 class="text-xl font-bold tracking-[-0.03em] text-slate-950" id="images-heading">{{ __('ui.listings.images_section') }}</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($listing->images as $image)
                                <figure class="overflow-hidden rounded-control border border-line bg-white">
                                    <img class="aspect-[4/3] w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->image_url) }}" alt="{{ __('ui.admin_moderation.image_number', ['number' => $loop->iteration]) }}" loading="lazy">
                                    <figcaption class="flex min-h-10 items-center justify-between gap-2 px-3 py-2 text-xs font-semibold text-slate-600">
                                        <span>{{ __('ui.admin_moderation.image_number', ['number' => $loop->iteration]) }}</span>
                                        @if ($image->is_cover)<span class="rounded-full bg-brand-50 px-2 py-1 text-brand-800">{{ __('ui.admin_moderation.cover') }}</span>@endif
                                    </figcaption>
                                </figure>
                            @endforeach
                        </div>
                    </section>

                    <section class="spatial-card p-4 sm:p-6" aria-labelledby="history-heading">
                        <h2 class="text-xl font-bold tracking-[-0.03em] text-slate-950" id="history-heading">{{ __('ui.admin_moderation.moderation_history') }}</h2>
                        <ol class="mt-4 divide-y divide-line">
                            @foreach ($listing->moderations as $historyItem)
                                <li class="grid gap-3 py-4 first:pt-0 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-bold text-slate-900">{{ __('ui.admin_moderation.version') }} {{ $historyItem->version_no }}</h3>
                                            <x-ui.status-badge axis="moderation" :value="$historyItem->status" />
                                        </div>
                                        <p class="mt-2 text-xs leading-5 text-slate-600">{{ __('ui.admin_moderation.submitted_at') }}: {{ $historyItem->submitted_at->format('d/m/Y H:i') }}</p>
                                        @if ($historyItem->reviewed_at)
                                            <p class="mt-1 text-xs leading-5 text-slate-600">{{ __('ui.admin_moderation.reviewed_at') }}: {{ $historyItem->reviewed_at->format('d/m/Y H:i') }}</p>
                                            <p class="mt-1 text-xs leading-5 text-slate-600">{{ __('ui.admin_moderation.reviewer') }}: {{ $historyItem->reviewer?->profile?->full_name ?? $historyItem->reviewer?->email ?? __('ui.admin_moderation.not_available') }}</p>
                                        @endif
                                        @if ($historyItem->rejection_reason)
                                            <p class="mt-2 rounded-control border border-danger-100 bg-danger-50 p-3 text-sm leading-6 text-danger-700">{{ $historyItem->rejection_reason }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                </div>

                <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
                    <section class="spatial-card p-4 sm:p-5" aria-labelledby="landlord-heading">
                        <h2 class="text-lg font-bold text-slate-950" id="landlord-heading">{{ __('ui.admin_moderation.landlord_profile') }}</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.full_name') }}</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ $listing->landlord->profile?->full_name ?? __('ui.admin_moderation.not_available') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.admin_moderation.account_id') }}</dt>
                                <dd class="mt-1 text-slate-700">{{ $listing->landlord->id }}</dd>
                            </div>
                            @if ($listing->landlord->email)
                                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.email') }}</dt><dd class="mt-1 break-all text-slate-700">{{ $listing->landlord->email }}</dd></div>
                            @endif
                            @if ($listing->landlord->phone)
                                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.phone') }}</dt><dd class="mt-1 text-slate-700">{{ $listing->landlord->phone }}</dd></div>
                            @endif
                            @if ($listing->landlord->profile?->contact_address)
                                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.contact_address') }}</dt><dd class="mt-1 leading-6 text-slate-700">{{ $listing->landlord->profile->contact_address }}</dd></div>
                            @endif
                            @if ($listing->landlord->profile?->zalo_number)
                                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.zalo_number') }}</dt><dd class="mt-1 text-slate-700">{{ $listing->landlord->profile->zalo_number }}</dd></div>
                            @endif
                        </dl>
                    </section>

                    @if ($moderation->status === 'PENDING')
                        <section class="spatial-card p-4 sm:p-5" aria-labelledby="decision-heading">
                            <h2 class="text-lg font-bold text-slate-950" id="decision-heading">{{ __('ui.admin_moderation.pending_action_heading') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('ui.admin_moderation.pending_action_description') }}</p>

                            <form class="mt-4" method="POST" action="{{ route('admin.listing-moderations.approve', [$listing, $moderation]) }}" data-submit-once>
                                @csrf
                                <x-ui.button class="w-full" type="submit">
                                    <i class="size-4" data-lucide="check"></i>
                                    {{ __('ui.admin_moderation.approve') }}
                                </x-ui.button>
                            </form>

                            <form class="mt-5 border-t border-line pt-5" method="POST" action="{{ route('admin.listing-moderations.reject', [$listing, $moderation]) }}" novalidate data-submit-once>
                                @csrf
                                <x-ui.error-summary :fields="['rejection_reason' => __('ui.admin_moderation.rejection_reason')]" id="moderation-errors" />
                                <x-ui.textarea
                                    name="rejection_reason"
                                    :label="__('ui.admin_moderation.rejection_reason')"
                                    :hint="__('ui.admin_moderation.rejection_reason_hint')"
                                    :value="null"
                                    rows="4"
                                    maxlength="1000"
                                    required
                                />
                                <x-ui.button class="mt-4 w-full" type="submit" variant="danger">
                                    <i class="size-4" data-lucide="circle-alert"></i>
                                    {{ __('ui.admin_moderation.reject') }}
                                </x-ui.button>
                            </form>
                        </section>
                    @endif
                </aside>
            </div>
        </div>
    </section>
@endsection
