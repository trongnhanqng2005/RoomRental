@extends('layouts.app')

@section('title', __('ui.reports.admin_title'))

@section('content')
    @php
        $reportStatusClasses = [
            'PENDING' => 'bg-warning-50 text-warning-700',
            'RESOLVED' => 'bg-success-50 text-success-700',
            'DISMISSED' => 'bg-slate-100 text-slate-700',
        ];
    @endphp

    <section class="flex-1 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <header class="max-w-3xl">
                <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.reports.admin_eyebrow') }}</p>
                <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950">{{ __('ui.reports.queue_heading') }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600">{{ __('ui.reports.queue_description') }}</p>
            </header>

            <form class="mt-6 flex flex-col gap-3 rounded-panel border border-line bg-white p-4 sm:flex-row sm:items-end" method="GET" action="{{ route('admin.reports.index') }}">
                <div class="w-full sm:max-w-xs">
                    <x-ui.select
                        name="status"
                        :label="__('ui.reports.status_filter')"
                        :options="[
                            'PENDING' => __('ui.reports.statuses.PENDING'),
                            'RESOLVED' => __('ui.reports.statuses.RESOLVED'),
                            'DISMISSED' => __('ui.reports.statuses.DISMISSED'),
                            'all' => __('ui.reports.all_statuses'),
                        ]"
                        :value="$status"
                    />
                </div>
                <x-ui.button class="w-full sm:w-auto" type="submit" variant="secondary">{{ __('ui.reports.apply_filter') }}</x-ui.button>
            </form>

            @if ($reports->isEmpty())
                <div class="spatial-card mt-7 p-8 text-center sm:p-12" role="status">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand-700"><i class="size-7" data-lucide="shield-check"></i></span>
                    <h2 class="mt-5 text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.reports.empty_heading') }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">{{ __('ui.reports.empty_description') }}</p>
                </div>
            @else
                <div class="mt-7 hidden overflow-hidden rounded-panel border border-line bg-white shadow-card lg:block">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[76rem] text-left text-sm">
                            <caption class="sr-only">{{ __('ui.reports.queue_heading') }}</caption>
                            <thead class="border-b border-line bg-slate-50 text-xs font-bold uppercase tracking-[0.1em] text-slate-500">
                                <tr>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.reports.report_id') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.reports.reporter') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.reports.listing') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.reports.landlord') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.reports.reason') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.reports.listing_state') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.reports.status') }}</th>
                                    <th class="px-5 py-4" scope="col"><span class="sr-only">{{ __('ui.reports.review') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($reports as $report)
                                    <tr class="align-top">
                                        <td class="whitespace-nowrap px-5 py-5">
                                            <p class="font-bold text-slate-950">#{{ $report->id }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $report->created_at->format('d/m/Y H:i') }}</p>
                                        </td>
                                        <td class="px-5 py-5">
                                            <p class="font-semibold text-slate-800">{{ $report->reporter->profile?->full_name ?? $report->reporter->email ?? $report->reporter->phone ?? '#'.$report->reporter_id }}</p>
                                        </td>
                                        <td class="max-w-64 px-5 py-5">
                                            <p class="break-words font-bold text-slate-950">{{ $report->listing->title }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ number_format((float) $report->listing->monthly_rent, 0, ',', '.') }} ₫</p>
                                        </td>
                                        <td class="px-5 py-5">
                                            <p class="font-semibold text-slate-800">{{ $report->listing->landlord->profile?->full_name ?? $report->listing->landlord->email ?? $report->listing->landlord->phone }}</p>
                                        </td>
                                        <td class="px-5 py-5 text-slate-700">{{ $report->reason->name }}</td>
                                        <td class="px-5 py-5">
                                            <p>{{ __('ui.listings.statuses.visibility.'.$report->listing->visibility_status) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ __('ui.listings.statuses.occupancy.'.$report->listing->occupancy_status) }} · {{ __('ui.listings.statuses.moderation.'.$report->listing->currentModeration?->status) }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-5">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $reportStatusClasses[$report->status] }}">{{ __('ui.reports.statuses.'.$report->status) }}</span>
                                        </td>
                                        <td class="px-5 py-5">
                                            <a class="inline-flex min-h-10 items-center justify-center gap-2 whitespace-nowrap rounded-control bg-brand-600 px-3 text-xs font-bold text-white transition hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600" href="{{ route('admin.reports.show', $report) }}">
                                                <i class="size-4" data-lucide="search"></i>
                                                {{ __('ui.reports.review') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 space-y-4 lg:hidden">
                    @foreach ($reports as $report)
                        <article class="spatial-card p-4 sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-bold text-slate-950">{{ __('ui.reports.report_id') }} #{{ $report->id }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $report->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $reportStatusClasses[$report->status] }}">{{ __('ui.reports.statuses.'.$report->status) }}</span>
                            </div>
                            <h2 class="mt-4 break-words text-base font-bold text-slate-950">{{ $report->listing->title }}</h2>
                            <dl class="mt-4 grid gap-3 border-t border-line pt-4 text-sm sm:grid-cols-2">
                                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.reports.reporter') }}</dt><dd class="mt-1 font-semibold text-slate-800">{{ $report->reporter->profile?->full_name ?? $report->reporter->email ?? $report->reporter->phone ?? '#'.$report->reporter_id }}</dd></div>
                                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.reports.landlord') }}</dt><dd class="mt-1 font-semibold text-slate-800">{{ $report->listing->landlord->profile?->full_name ?? $report->listing->landlord->email ?? $report->listing->landlord->phone }}</dd></div>
                                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.reports.reason') }}</dt><dd class="mt-1 text-slate-700">{{ $report->reason->name }}</dd></div>
                                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.reports.listing_state') }}</dt><dd class="mt-1 text-slate-700">{{ __('ui.listings.statuses.visibility.'.$report->listing->visibility_status) }} · {{ __('ui.listings.statuses.occupancy.'.$report->listing->occupancy_status) }} · {{ __('ui.listings.statuses.moderation.'.$report->listing->currentModeration?->status) }}</dd></div>
                            </dl>
                            <a class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-control bg-brand-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600" href="{{ route('admin.reports.show', $report) }}">
                                <i class="size-4" data-lucide="search"></i>
                                {{ __('ui.reports.review') }}
                            </a>
                        </article>
                    @endforeach
                </div>

                <nav class="mt-6" aria-label="{{ __('ui.reports.pagination') }}">{{ $reports->links() }}</nav>
            @endif
        </div>
    </section>
@endsection
