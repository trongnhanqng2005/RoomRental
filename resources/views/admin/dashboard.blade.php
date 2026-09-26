@extends('layouts.app')

@section('title', __('ui.dashboard.admin_title'))

@section('content')
    <section class="flex-1 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <header class="max-w-3xl">
                <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.dashboard.admin_eyebrow') }}</p>
                <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950">{{ __('ui.dashboard.admin_heading') }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.dashboard.admin_description') }}</p>
            </header>

            <div class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <a class="spatial-card flex min-h-28 items-center gap-4 p-4 transition hover:border-brand-200 hover:bg-brand-50/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 sm:p-5" href="{{ route('admin.users.index') }}">
                    <span class="grid size-11 shrink-0 place-items-center rounded-control bg-brand-50 text-brand-700" aria-hidden="true"><i class="size-5" data-lucide="users"></i></span>
                    <span><span class="block text-xs font-bold uppercase tracking-[0.08em] text-slate-500">{{ __('ui.dashboard.total_users') }}</span><span class="mt-1 block text-2xl font-bold text-slate-950">{{ number_format($metrics['total_users']) }}</span></span>
                </a>
                <a class="spatial-card flex min-h-28 items-center gap-4 p-4 transition hover:border-brand-200 hover:bg-brand-50/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 sm:p-5" href="{{ route('admin.users.index', ['role' => 'LANDLORD']) }}">
                    <span class="grid size-11 shrink-0 place-items-center rounded-control bg-accent-50 text-accent-700" aria-hidden="true"><i class="size-5" data-lucide="building-2"></i></span>
                    <span><span class="block text-xs font-bold uppercase tracking-[0.08em] text-slate-500">{{ __('ui.dashboard.total_landlords') }}</span><span class="mt-1 block text-2xl font-bold text-slate-950">{{ number_format($metrics['total_landlords']) }}</span></span>
                </a>
                <a class="spatial-card flex min-h-28 items-center gap-4 p-4 transition hover:border-brand-200 hover:bg-brand-50/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 sm:p-5" href="{{ route('admin.reports.index') }}">
                    <span class="grid size-11 shrink-0 place-items-center rounded-control bg-warning-50 text-warning-700" aria-hidden="true"><i class="size-5" data-lucide="flag"></i></span>
                    <span><span class="block text-xs font-bold uppercase tracking-[0.08em] text-slate-500">{{ __('ui.dashboard.pending_reports') }}</span><span class="mt-1 block text-2xl font-bold text-slate-950">{{ number_format($metrics['pending_reports']) }}</span></span>
                </a>
                <article class="spatial-card flex min-h-28 items-center gap-4 p-4 sm:p-5">
                    <span class="grid size-11 shrink-0 place-items-center rounded-control bg-slate-100 text-slate-700" aria-hidden="true"><i class="size-5" data-lucide="calendar-days"></i></span>
                    <div><p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">{{ __('ui.dashboard.today_appointments') }}</p><p class="mt-1 text-2xl font-bold text-slate-950">{{ number_format($metrics['today_appointments']) }}</p></div>
                </article>
            </div>

            <section class="spatial-card mt-6 p-4 sm:p-6" aria-labelledby="listing-states-heading">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-lg font-bold text-slate-950" id="listing-states-heading">{{ __('ui.dashboard.listing_states') }}</h2>
                    <p class="text-sm font-semibold text-slate-500">{{ __('ui.dashboard.inventory_total', ['count' => number_format($listingStates['total_listings'])]) }}</p>
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    @foreach (['moderation' => ['heading' => 'moderation_axis', 'statuses' => $listingStates['moderation']], 'occupancy' => ['heading' => 'occupancy_axis', 'statuses' => $listingStates['occupancy']], 'visibility' => ['heading' => 'visibility_axis', 'statuses' => $listingStates['visibility']]] as $axis => $group)
                        <section class="rounded-control border border-line bg-white p-4" aria-labelledby="{{ $axis }}-axis-heading">
                            <h3 class="text-sm font-bold text-slate-800" id="{{ $axis }}-axis-heading">{{ __('ui.dashboard.'.$group['heading']) }}</h3>
                            <dl class="mt-3 space-y-2">
                                @foreach ($group['statuses'] as $status => $count)
                                    <div class="flex items-center justify-between gap-3 border-t border-line pt-2 first:border-0 first:pt-0">
                                        <dt class="text-sm text-slate-600">{{ __('ui.listings.statuses.'.$axis.'.'.$status) }}</dt>
                                        <dd class="font-bold tabular-nums text-slate-950">
                                            @if ($axis === 'moderation' && $status === 'PENDING')
                                                <a class="rounded-sm underline decoration-brand-600/50 underline-offset-2 hover:text-brand-800" href="{{ route('admin.listing-moderations.index') }}" aria-label="{{ __('ui.dashboard.pending_moderation_link_label', ['count' => number_format($count)]) }}">{{ number_format($count) }}</a>
                                            @else
                                                {{ number_format($count) }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </section>
                    @endforeach
                </div>
            </section>

            <div class="mt-6 grid gap-4 xl:grid-cols-2">
                <section class="spatial-card min-w-0 p-4 sm:p-6" aria-labelledby="monthly-listings-heading">
                    <h2 class="text-lg font-bold text-slate-950" id="monthly-listings-heading">{{ __('ui.dashboard.monthly_listings') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ __('ui.dashboard.monthly_listings_description') }}</p>
                    @php($maximumMonthlyCount = max(1, collect($monthlyListings)->max('count')))
                    @if (collect($monthlyListings)->max('count') === 0)
                        <p class="mt-4 rounded-control border border-line bg-slate-50 p-3 text-sm text-slate-600" role="status">{{ __('ui.dashboard.empty_monthly') }}</p>
                    @endif
                    <ol class="mt-4 space-y-2" aria-label="{{ __('ui.dashboard.monthly_listings') }}">
                        @foreach ($monthlyListings as $month)
                            <li class="grid min-w-0 grid-cols-[4.5rem_minmax(0,1fr)_2.5rem] items-center gap-2 sm:grid-cols-[5.5rem_minmax(0,1fr)_3rem] sm:gap-3">
                                <span class="text-xs font-semibold text-slate-600 sm:text-sm">{{ $month['label'] }}</span>
                                <span class="h-3 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><span class="block h-full rounded-full bg-brand-600" style="width: {{ $month['count'] === 0 ? 0 : ($month['count'] / $maximumMonthlyCount) * 100 }}%"></span></span>
                                <span class="text-right text-sm font-bold tabular-nums text-slate-900">{{ number_format($month['count']) }}</span>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <section class="spatial-card min-w-0 p-4 sm:p-6" aria-labelledby="category-distribution-heading">
                    <h2 class="text-lg font-bold text-slate-950" id="category-distribution-heading">{{ __('ui.dashboard.category_distribution') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ __('ui.dashboard.category_distribution_description') }}</p>
                    @if ($categoryDistribution['total_listings'] === 0)
                        <p class="mt-4 rounded-control border border-line bg-slate-50 p-3 text-sm text-slate-600" role="status">{{ __('ui.dashboard.empty_categories') }}</p>
                    @else
                        <ol class="mt-4 space-y-4" aria-label="{{ __('ui.dashboard.category_distribution') }}">
                            @foreach ($categoryDistribution['categories'] as $category)
                                <li class="min-w-0">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-sm">
                                        <span class="min-w-0 break-words font-semibold text-slate-800">{{ $category['name'] ?? __('ui.dashboard.unknown_category') }}@if ($category['is_active'] === false) <span class="text-xs font-medium text-slate-500">({{ __('ui.dashboard.hidden_category') }})</span>@endif</span>
                                        <span class="shrink-0 font-bold tabular-nums text-slate-900">{{ number_format($category['count']) }} · {{ number_format($category['percentage'], 1) }}%</span>
                                    </div>
                                    <span class="mt-2 block h-2.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><span class="block h-full rounded-full bg-accent-600" style="width: {{ $category['percentage'] }}%"></span></span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </div>

            <div class="mt-6 grid gap-4 xl:grid-cols-2">
                <section class="spatial-card min-w-0 p-4 sm:p-6" aria-labelledby="recent-moderation-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-slate-950" id="recent-moderation-heading">{{ __('ui.dashboard.recent_moderation') }}</h2>
                        <a class="inline-flex min-h-10 items-center rounded-control px-3 text-sm font-bold text-brand-700 underline underline-offset-2 hover:bg-brand-50" href="{{ route('admin.listing-moderations.index') }}">{{ __('ui.dashboard.view_moderation_queue') }}</a>
                    </div>
                    @if ($recentPendingModeration->isEmpty())
                        <p class="mt-3 rounded-control border border-line bg-slate-50 p-3 text-sm text-slate-600" role="status">{{ __('ui.dashboard.empty_moderation') }}</p>
                    @else
                        <ul class="mt-3 divide-y divide-line">
                            @foreach ($recentPendingModeration as $listing)
                                <li class="flex min-w-0 flex-col gap-1 py-3 first:pt-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                    <a class="min-w-0 break-words font-semibold text-slate-800 underline decoration-brand-600/40 underline-offset-2 hover:text-brand-800" href="{{ route('admin.listing-moderations.show', $listing) }}">{{ $listing->title }}</a>
                                    <span class="shrink-0 text-xs text-slate-500">{{ __('ui.dashboard.submitted_at', ['date' => $listing->currentModeration->submitted_at->format('d/m/Y H:i')]) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="spatial-card min-w-0 p-4 sm:p-6" aria-labelledby="recent-reports-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-slate-950" id="recent-reports-heading">{{ __('ui.dashboard.recent_reports') }}</h2>
                        <a class="inline-flex min-h-10 items-center rounded-control px-3 text-sm font-bold text-brand-700 underline underline-offset-2 hover:bg-brand-50" href="{{ route('admin.reports.index') }}">{{ __('ui.dashboard.view_report_queue') }}</a>
                    </div>
                    @if ($recentPendingReports->isEmpty())
                        <p class="mt-3 rounded-control border border-line bg-slate-50 p-3 text-sm text-slate-600" role="status">{{ __('ui.dashboard.empty_reports') }}</p>
                    @else
                        <ul class="mt-3 divide-y divide-line">
                            @foreach ($recentPendingReports as $report)
                                <li class="flex min-w-0 flex-col gap-1 py-3 first:pt-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                    <div class="min-w-0">
                                        <a class="block break-words font-semibold text-slate-800 underline decoration-brand-600/40 underline-offset-2 hover:text-brand-800" href="{{ route('admin.reports.show', $report) }}">{{ $report->listing?->title ?? __('ui.dashboard.unknown_listing') }}</a>
                                        @if ($report->listing?->deleted_at)
                                            <span class="mt-1 inline-block rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ __('ui.dashboard.deleted_listing') }}</span>
                                        @endif
                                    </div>
                                    <span class="shrink-0 text-xs text-slate-500">{{ __('ui.dashboard.reported_at', ['date' => $report->created_at->format('d/m/Y H:i')]) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>
    </section>
@endsection
