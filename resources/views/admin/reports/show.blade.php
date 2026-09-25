@extends('layouts.app')

@section('title', __('ui.reports.detail_title', ['id' => $report->id]))

@section('content')
    @php
        $listing = $report->listing;
        $landlord = $listing->landlord;
        $address = implode(', ', array_filter([
            $listing->street_address,
            $listing->ward->name,
            $listing->ward->district->name,
            $listing->ward->district->province->name,
        ]));
        $coverImage = $listing->images->firstWhere('is_cover', true) ?? $listing->images->first();
        $reportStatusClasses = [
            'PENDING' => 'bg-warning-50 text-warning-700',
            'RESOLVED' => 'bg-success-50 text-success-700',
            'DISMISSED' => 'bg-slate-100 text-slate-700',
        ];
    @endphp

    <section class="flex-1 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <a class="inline-flex min-h-10 items-center gap-2 rounded-control px-2 text-sm font-bold text-brand-700 transition hover:bg-brand-50 hover:text-brand-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600" href="{{ route('admin.reports.index') }}">
                <i class="size-4 rotate-180" data-lucide="arrow-right" aria-hidden="true"></i>
                {{ __('ui.reports.back_to_queue') }}
            </a>

            <header class="mt-4 flex flex-col gap-4 border-b border-line pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.reports.admin_eyebrow') }} · #{{ $report->id }}</p>
                    <h1 class="break-words text-3xl font-bold tracking-[-0.04em] text-slate-950">{{ $listing->title }}</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $address }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <div><span class="mb-1 block text-xs font-bold text-slate-500">{{ __('ui.reports.status') }}</span><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $reportStatusClasses[$report->status] }}">{{ __('ui.reports.statuses.'.$report->status) }}</span></div>
                    <div><span class="mb-1 block text-xs font-bold text-slate-500">{{ __('ui.listings.moderation_short') }}</span><x-ui.status-badge axis="moderation" :value="$listing->currentModeration?->status ?? 'PENDING'" /></div>
                    <div><span class="mb-1 block text-xs font-bold text-slate-500">{{ __('ui.listings.occupancy_short') }}</span><x-ui.status-badge axis="occupancy" :value="$listing->occupancy_status" /></div>
                    <div><span class="mb-1 block text-xs font-bold text-slate-500">{{ __('ui.listings.visibility_short') }}</span><x-ui.status-badge axis="visibility" :value="$listing->visibility_status" /></div>
                    @if ($listing->expires_at)
                        <div><span class="mb-1 block text-xs font-bold text-slate-500">{{ __('ui.listings.expires_at') }}</span><span class="text-sm font-semibold text-slate-700">{{ $listing->expires_at->format('d/m/Y H:i') }}</span></div>
                    @endif
                </div>
            </header>

            @if (session('status'))
                <div class="mt-6 flex items-start gap-3 rounded-control border border-success-100 bg-success-50 p-4 text-sm font-semibold leading-6 text-success-700" role="status" aria-live="polite">
                    <i class="mt-0.5 size-5 shrink-0" data-lucide="check"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($listing->deleted_at)
                <p class="mt-5 rounded-control border border-warning-100 bg-warning-50 p-3 text-sm font-semibold text-warning-800" role="status">{{ __('ui.reports.listing_deleted') }}</p>
            @endif

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="min-w-0 space-y-6">
                    <section class="spatial-card p-4 sm:p-6" aria-labelledby="report-information-heading">
                        <h2 class="text-xl font-bold tracking-[-0.03em] text-slate-950" id="report-information-heading">{{ __('ui.reports.report_information') }}</h2>
                        <dl class="mt-4 grid gap-4 border-t border-line pt-4 sm:grid-cols-2">
                            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.reports.report_id') }}</dt><dd class="mt-1 font-semibold text-slate-800">#{{ $report->id }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.reports.created_at') }}</dt><dd class="mt-1 text-slate-700">{{ $report->created_at->format('d/m/Y H:i') }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.reports.reason') }}</dt><dd class="mt-1 font-semibold text-slate-800">{{ $report->reason->name }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.reports.description_field') }}</dt><dd class="mt-1 whitespace-pre-line break-words text-sm leading-7 text-slate-700">{{ $report->description ?: __('ui.reports.no_description') }}</dd></div>
                            @if ($report->resolution_reason)
                                <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.reports.resolution_reason') }}</dt><dd class="mt-1 whitespace-pre-line break-words text-sm leading-7 text-slate-700">{{ $report->resolution_reason }}</dd></div>
                            @endif
                        </dl>
                    </section>

                    <section class="spatial-card p-4 sm:p-6" aria-labelledby="listing-content-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold tracking-[-0.03em] text-slate-950" id="listing-content-heading">{{ __('ui.reports.listing_content') }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ $listing->category->name }}</p>
                            </div>
                            <p class="text-lg font-bold text-slate-950">{{ number_format((float) $listing->monthly_rent, 0, ',', '.') }} ₫ <span class="text-xs font-semibold text-slate-500">/ {{ __('ui.listings.monthly_rent') }}</span></p>
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
                                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.listings.'.$label) }}</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $value }}</dd></div>
                            @endforeach
                        </dl>
                        <div class="mt-5 border-t border-line pt-5">
                            <h3 class="text-sm font-bold text-slate-800">{{ __('ui.reports.full_address') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ $address }}</p>
                        </div>
                        @if ($listing->amenities->isNotEmpty())
                            <div class="mt-5 border-t border-line pt-5">
                                <h3 class="text-sm font-bold text-slate-800">{{ __('ui.listings.amenities') }}</h3>
                                <ul class="mt-2 flex flex-wrap gap-2">@foreach ($listing->amenities as $amenity)<li class="rounded-full border border-line bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">{{ $amenity->name }}</li>@endforeach</ul>
                            </div>
                        @endif
                        @if ($coverImage)
                            <figure class="mt-5 overflow-hidden rounded-control border border-line bg-slate-100">
                                <img class="aspect-[16/10] w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($coverImage->image_url) }}" alt="{{ __('ui.reports.cover_image_alt', ['title' => $listing->title]) }}" loading="lazy">
                            </figure>
                        @endif
                    </section>

                    <section class="spatial-card p-4 sm:p-6" aria-labelledby="enforcement-history-heading">
                        <h2 class="text-xl font-bold tracking-[-0.03em] text-slate-950" id="enforcement-history-heading">{{ __('ui.reports.enforcement_history') }}</h2>
                        @if ($report->enforcementActions->isEmpty())
                            <p class="mt-3 text-sm text-slate-500">{{ __('ui.reports.no_enforcement_history') }}</p>
                        @else
                            <ol class="mt-4 divide-y divide-line">
                                @foreach ($report->enforcementActions as $action)
                                    <li class="py-4 first:pt-0">
                                        <p class="font-bold text-slate-900">{{ __('ui.reports.enforcement_types.'.$action->action_type) }}</p>
                                        <p class="mt-1 text-sm leading-6 text-slate-700">{{ $action->reason }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $action->created_at->format('d/m/Y H:i') }} · {{ $action->admin->profile?->full_name ?? '#'.$action->admin_id }}</p>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </section>
                </div>

                <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
                    <section class="spatial-card p-4 sm:p-5" aria-labelledby="reporter-heading">
                        <h2 class="text-lg font-bold text-slate-950" id="reporter-heading">{{ __('ui.reports.reporter') }}</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.full_name') }}</dt><dd class="mt-1 font-semibold text-slate-800">{{ $report->reporter->profile?->full_name ?? __('ui.reports.not_available') }}</dd></div>
                            @if ($report->reporter->email)<div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.email') }}</dt><dd class="mt-1 break-all text-slate-700">{{ $report->reporter->email }}</dd></div>@endif
                            @if ($report->reporter->phone)<div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.phone') }}</dt><dd class="mt-1 text-slate-700">{{ $report->reporter->phone }}</dd></div>@endif
                        </dl>
                    </section>

                    <section class="spatial-card p-4 sm:p-5" aria-labelledby="landlord-heading">
                        <h2 class="text-lg font-bold text-slate-950" id="landlord-heading">{{ __('ui.reports.landlord') }}</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.full_name') }}</dt><dd class="mt-1 font-semibold text-slate-800">{{ $landlord->profile?->full_name ?? __('ui.reports.not_available') }}</dd></div>
                            @if ($landlord->email)<div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.email') }}</dt><dd class="mt-1 break-all text-slate-700">{{ $landlord->email }}</dd></div>@endif
                            @if ($landlord->phone)<div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.phone') }}</dt><dd class="mt-1 text-slate-700">{{ $landlord->phone }}</dd></div>@endif
                            @if ($landlord->profile?->zalo_number)<div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.profile.zalo_number') }}</dt><dd class="mt-1 text-slate-700">{{ $landlord->profile->zalo_number }}</dd></div>@endif
                            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.reports.account_status') }}</dt><dd class="mt-1 font-semibold text-slate-700">{{ __('ui.reports.account_statuses.'.$landlord->account_status) }}</dd></div>
                        </dl>
                    </section>

                    @if ($listing->visibility_status === 'SUSPENDED')
                        <section class="spatial-card p-4 sm:p-5" aria-labelledby="unsuspend-heading">
                            <h2 class="text-lg font-bold text-slate-950" id="unsuspend-heading">{{ __('ui.listings.unsuspend') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('ui.listings.unsuspend_description') }}</p>
                            <form class="mt-4" method="POST" action="{{ route('admin.listings.unsuspend', $listing) }}" onsubmit="return confirm(@js(__('ui.listings.unsuspend_confirmation')))" data-submit-once>
                                @csrf
                                @method('PATCH')
                                <x-ui.button class="w-full" type="submit" variant="secondary"><i class="size-4" data-lucide="shield-check" aria-hidden="true"></i>{{ __('ui.listings.unsuspend') }}</x-ui.button>
                            </form>
                        </section>
                    @endif

                    @if ($report->status === 'PENDING')
                        <section class="spatial-card p-4 sm:p-5" aria-labelledby="decision-heading">
                            <h2 class="text-lg font-bold text-slate-950" id="decision-heading">{{ __('ui.reports.decision_heading') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('ui.reports.decision_description') }}</p>

                            <form class="mt-4 border-t border-line pt-4" method="POST" action="{{ route('admin.reports.dismiss', $report) }}" data-submit-once>
                                @csrf
                                <x-ui.error-summary :fields="['resolution_reason' => __('ui.reports.resolution_reason')]" id="dismiss-report-errors" />
                                <x-ui.textarea name="resolution_reason" :label="__('ui.reports.resolution_reason')" :hint="__('ui.reports.dismiss_reason_hint')" rows="3" maxlength="1000" required />
                                <x-ui.button class="mt-4 w-full" type="submit" variant="danger"><i class="size-4" data-lucide="circle-x"></i>{{ __('ui.reports.dismiss') }}</x-ui.button>
                            </form>

                            <form class="mt-5 border-t border-line pt-5" method="POST" action="{{ route('admin.reports.resolve', $report) }}" data-submit-once>
                                @csrf
                                <x-ui.error-summary :fields="['action_type' => __('ui.reports.enforcement_action'), 'reason' => __('ui.reports.enforcement_reason')]" id="resolve-report-errors" />
                                <x-ui.select
                                    name="action_type"
                                    :label="__('ui.reports.enforcement_action')"
                                    :options="[
                                        'WARNING' => __('ui.reports.enforcement_types.WARNING'),
                                        'SUSPEND_LISTING' => __('ui.reports.enforcement_types.SUSPEND_LISTING'),
                                        'LOCK_ACCOUNT' => __('ui.reports.enforcement_types.LOCK_ACCOUNT'),
                                    ]"
                                    :placeholder="__('ui.reports.choose_action')"
                                    :required="true"
                                />
                                <div class="mt-4 space-y-2 rounded-control border border-warning-100 bg-warning-50 p-3 text-xs leading-5 text-warning-900">
                                    <p><strong>{{ __('ui.reports.enforcement_types.SUSPEND_LISTING') }}:</strong> {{ __('ui.reports.suspend_consequence') }}</p>
                                    <p><strong>{{ __('ui.reports.enforcement_types.LOCK_ACCOUNT') }}:</strong> {{ __('ui.reports.lock_consequence') }}</p>
                                </div>
                                <x-ui.textarea class="mt-4" name="reason" :label="__('ui.reports.enforcement_reason')" :hint="__('ui.reports.enforcement_reason_hint')" rows="3" maxlength="1000" required />
                                <x-ui.button class="mt-4 w-full" type="submit"><i class="size-4" data-lucide="shield-check"></i>{{ __('ui.reports.resolve') }}</x-ui.button>
                            </form>
                        </section>
                    @endif
                </aside>
            </div>
        </div>
    </section>
@endsection
