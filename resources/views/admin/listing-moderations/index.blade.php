@extends('layouts.app')

@section('title', __('ui.admin_moderation.title'))

@section('content')
    <section class="flex-1 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <header class="max-w-3xl">
                <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.admin_moderation.eyebrow') }}</p>
                <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950">{{ __('ui.admin_moderation.heading') }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600">{{ __('ui.admin_moderation.description') }}</p>
            </header>

            @if (session('status'))
                <div class="mt-6 flex items-start gap-3 rounded-control border border-success-100 bg-success-50 p-4 text-sm font-semibold leading-6 text-success-700" role="status" aria-live="polite">
                    <i class="mt-0.5 size-5 shrink-0" data-lucide="check"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($listings->isEmpty())
                <div class="spatial-card mt-7 p-8 text-center sm:p-12" role="status">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand-700"><i class="size-7" data-lucide="shield-check"></i></span>
                    <h2 class="mt-5 text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.admin_moderation.empty_heading') }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">{{ __('ui.admin_moderation.empty_description') }}</p>
                </div>
            @else
                <div class="mt-7 hidden overflow-hidden rounded-panel border border-line bg-white shadow-card lg:block">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[72rem] text-left text-sm">
                            <caption class="sr-only">{{ __('ui.admin_moderation.queue') }}</caption>
                            <thead class="border-b border-line bg-slate-50 text-xs font-bold uppercase tracking-[0.1em] text-slate-500">
                                <tr>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.listings.listing') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.admin_moderation.landlord') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.admin_moderation.location') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.listings.monthly_rent') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.admin_moderation.submitted_at') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.admin_moderation.version') }}</th>
                                    <th class="px-5 py-4" scope="col"><span class="sr-only">{{ __('ui.admin_moderation.review') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($listings as $listing)
                                    <tr class="align-top">
                                        <td class="px-5 py-5">
                                            <p class="font-bold text-slate-950">{{ $listing->title }}</p>
                                            <p class="mt-1 text-xs font-semibold text-brand-700">{{ $listing->category->name }}</p>
                                        </td>
                                        <td class="px-5 py-5">
                                            <p class="font-semibold text-slate-800">{{ $listing->landlord->profile?->full_name ?? $listing->landlord->email ?? $listing->landlord->phone }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $listing->landlord->email ?? $listing->landlord->phone }}</p>
                                        </td>
                                        <td class="max-w-64 px-5 py-5 text-slate-600">
                                            <p>{{ $listing->street_address }}</p>
                                            <p class="mt-1 text-xs">{{ $listing->ward->name }}, {{ $listing->ward->district->name }}, {{ $listing->ward->district->province->name }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-5 font-bold text-slate-900">{{ number_format((float) $listing->monthly_rent, 0, ',', '.') }} ₫</td>
                                        <td class="whitespace-nowrap px-5 py-5 text-slate-600">{{ $listing->currentModeration->submitted_at->format('d/m/Y H:i') }}</td>
                                        <td class="whitespace-nowrap px-5 py-5 font-semibold text-slate-700">{{ __('ui.admin_moderation.version') }} {{ $listing->currentModeration->version_no }}</td>
                                        <td class="px-5 py-5">
                                            <a class="inline-flex min-h-10 items-center justify-center gap-2 rounded-control bg-brand-600 px-3 text-xs font-bold text-white transition hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600" href="{{ route('admin.listing-moderations.show', $listing) }}">
                                                <i class="size-4" data-lucide="search"></i>
                                                {{ __('ui.admin_moderation.review') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 space-y-4 lg:hidden">
                    @foreach ($listings as $listing)
                        <article class="spatial-card p-4 sm:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="break-words text-base font-bold text-slate-950">{{ $listing->title }}</p>
                                    <p class="mt-1 text-sm font-semibold text-brand-700">{{ $listing->category->name }}</p>
                                </div>
                                <x-ui.status-badge axis="moderation" :value="$listing->currentModeration->status" />
                            </div>
                            <dl class="mt-4 grid gap-x-4 gap-y-3 border-t border-line pt-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.admin_moderation.landlord') }}</dt>
                                    <dd class="mt-1 font-semibold text-slate-800">{{ $listing->landlord->profile?->full_name ?? $listing->landlord->email ?? $listing->landlord->phone }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.listings.monthly_rent') }}</dt>
                                    <dd class="mt-1 font-bold text-slate-900">{{ number_format((float) $listing->monthly_rent, 0, ',', '.') }} ₫</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.admin_moderation.location') }}</dt>
                                    <dd class="mt-1 leading-6 text-slate-700">{{ $listing->street_address }}, {{ $listing->ward->name }}, {{ $listing->ward->district->name }}, {{ $listing->ward->district->province->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.admin_moderation.submitted_at') }}</dt>
                                    <dd class="mt-1 text-slate-700">{{ $listing->currentModeration->submitted_at->format('d/m/Y H:i') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.admin_moderation.version') }}</dt>
                                    <dd class="mt-1 font-semibold text-slate-700">{{ $listing->currentModeration->version_no }}</dd>
                                </div>
                            </dl>
                            <a class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-control bg-brand-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700" href="{{ route('admin.listing-moderations.show', $listing) }}">
                                <i class="size-4" data-lucide="search"></i>
                                {{ __('ui.admin_moderation.review') }}
                            </a>
                        </article>
                    @endforeach
                </div>

                <div class="mt-6">{{ $listings->links() }}</div>
            @endif
        </div>
    </section>
@endsection
