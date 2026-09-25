@extends('layouts.app')

@section('title', __('ui.listings.management_title'))

@section('content')
    <section class="flex-1 py-10 sm:py-14 lg:py-16">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-3xl">
                    <p class="mb-3 text-sm font-bold text-brand-700">{{ __('ui.listings.eyebrow') }}</p>
                    <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950 sm:text-4xl">{{ __('ui.listings.heading') }}</h1>
                    <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.listings.management_description') }}</p>
                </div>
                <a class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-control bg-brand-600 px-4 py-2.5 text-sm font-bold text-white shadow-[0_10px_24px_-12px_rgb(15_118_110_/_0.9)] transition hover:bg-brand-700" href="{{ route('landlord.listings.create') }}">
                    <i class="size-[18px]" data-lucide="plus"></i>
                    {{ __('ui.listings.new_listing') }}
                </a>
            </header>

            @if (session('status'))
                <div class="mt-7 flex items-start gap-3 rounded-control border border-success-100 bg-success-50 p-4 text-sm font-semibold leading-6 text-success-700" role="status" aria-live="polite">
                    <i class="mt-0.5 size-5 shrink-0" data-lucide="check"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif
            @if (session('duplicate_warning'))
                <div class="mt-4 flex items-start gap-3 rounded-control border border-warning-100 bg-warning-50 p-4 text-sm font-semibold leading-6 text-warning-900" role="status" aria-live="polite">
                    <i class="mt-0.5 size-5 shrink-0" data-lucide="circle-alert" aria-hidden="true"></i>
                    <span>{{ session('duplicate_warning') }}</span>
                </div>
            @endif

            <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([['total', 'list', 'brand'], ['pending', 'clock-3', 'warning'], ['available', 'door-open', 'accent'], ['views', 'eye', 'slate']] as [$key, $icon, $tone])
                    <div class="spatial-card flex items-center gap-4 p-4 sm:p-5">
                        <span class="grid size-11 shrink-0 place-items-center rounded-control {{ $tone === 'brand' ? 'bg-brand-50 text-brand-700' : ($tone === 'warning' ? 'bg-warning-50 text-warning-700' : ($tone === 'accent' ? 'bg-accent-50 text-accent-700' : 'bg-slate-100 text-slate-700')) }}"><i class="size-5" data-lucide="{{ $icon }}"></i></span>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">{{ __('ui.listings.'.$key) }}</p>
                            <p class="mt-1 text-2xl font-bold tracking-[-0.04em] text-slate-950">{{ number_format($stats[$key]) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <form class="spatial-card mt-6 grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(14rem,1.5fr)_repeat(3,minmax(10rem,1fr))_minmax(10rem,1fr)_auto] lg:items-end" method="GET" action="{{ route('landlord.listings.index') }}">
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.12em] text-slate-500" for="listing-search">{{ __('ui.listings.listing') }}</label>
                    <input class="field-control" id="listing-search" name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('ui.listings.search_placeholder') }}">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.12em] text-slate-500" for="moderation-status">{{ __('ui.listings.moderation_short') }}</label>
                    <select class="field-control" id="moderation-status" name="moderation_status">
                        <option value="">{{ __('ui.listings.all_moderation') }}</option>
                        @foreach (['PENDING', 'APPROVED', 'REJECTED'] as $status)
                            <option value="{{ $status }}" @selected(request('moderation_status') === $status)>{{ __('ui.listings.statuses.moderation.'.$status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.12em] text-slate-500" for="occupancy-status">{{ __('ui.listings.occupancy_short') }}</label>
                    <select class="field-control" id="occupancy-status" name="occupancy_status">
                        <option value="">{{ __('ui.listings.all_occupancy') }}</option>
                        @foreach (['AVAILABLE', 'RENTED'] as $status)
                            <option value="{{ $status }}" @selected(request('occupancy_status') === $status)>{{ __('ui.listings.statuses.occupancy.'.$status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.12em] text-slate-500" for="visibility-status">{{ __('ui.listings.visibility_short') }}</label>
                    <select class="field-control" id="visibility-status" name="visibility_status">
                        <option value="">{{ __('ui.listings.all_visibility') }}</option>
                        @foreach (['VISIBLE', 'HIDDEN', 'SUSPENDED'] as $status)
                            <option value="{{ $status }}" @selected(request('visibility_status') === $status)>{{ __('ui.listings.statuses.visibility.'.$status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.12em] text-slate-500" for="listing-sort">{{ __('ui.listings.updated_at') }}</label>
                    <select class="field-control" id="listing-sort" name="sort">
                        @foreach (['updated_at' => __('ui.listings.sort_updated'), 'oldest' => __('ui.listings.sort_oldest'), 'rent_asc' => __('ui.listings.sort_rent_asc'), 'rent_desc' => __('ui.listings.sort_rent_desc')] as $value => $label)
                            <option value="{{ $value }}" @selected(request('sort', 'updated_at') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.button class="w-full lg:w-auto" type="submit" variant="secondary"><i class="size-4" data-lucide="list-filter"></i>{{ __('ui.listings.apply_filters') }}</x-ui.button>
            </form>

            @if ($listings->isEmpty())
                <div class="spatial-card mt-6 p-8 text-center sm:p-12" role="status">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand-700"><i class="size-7" data-lucide="building-2"></i></span>
                    <h2 class="mt-5 text-xl font-bold tracking-[-0.03em] text-slate-950">{{ request()->hasAny(['q', 'moderation_status', 'occupancy_status', 'visibility_status']) ? __('ui.listings.no_results') : __('ui.listings.empty_heading') }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">{{ request()->hasAny(['q', 'moderation_status', 'occupancy_status', 'visibility_status']) ? __('ui.listings.no_results') : __('ui.listings.empty_description') }}</p>
                    @if (request()->hasAny(['q', 'moderation_status', 'occupancy_status', 'visibility_status']))
                        <a class="mt-6 inline-flex min-h-11 items-center justify-center rounded-control border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700" href="{{ route('landlord.listings.index') }}">{{ __('ui.listings.clear_filters') }}</a>
                    @else
                        <a class="mt-6 inline-flex min-h-11 items-center justify-center gap-2 rounded-control bg-brand-600 px-4 py-2.5 text-sm font-bold text-white" href="{{ route('landlord.listings.create') }}"><i class="size-4" data-lucide="plus"></i>{{ __('ui.listings.first_listing') }}</a>
                    @endif
                </div>
            @else
                <div class="mt-6 hidden overflow-hidden rounded-panel border border-line bg-white shadow-card lg:block">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[62rem] text-left text-sm">
                            <caption class="sr-only">{{ __('ui.listings.heading') }}</caption>
                            <thead class="border-b border-line bg-slate-50 text-xs font-bold uppercase tracking-[0.12em] text-slate-500">
                                <tr>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.listings.listing') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.listings.rent_short') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.listings.moderation_short') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.listings.occupancy_short') }}</th>
                                    <th class="px-5 py-4" scope="col">{{ __('ui.listings.visibility_short') }}</th>
                                    <th class="px-5 py-4" scope="col"><span class="sr-only">{{ __('ui.listings.edit') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($listings as $listing)
                                    <tr class="align-top">
                                        <td class="px-5 py-5">
                                            <div class="flex min-w-80 items-start gap-3">
                                                @if ($listing->coverImage)
                                                    <img class="size-16 shrink-0 rounded-control object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($listing->coverImage->image_url) }}" alt="" loading="lazy">
                                                @else
                                                    <span class="grid size-16 shrink-0 place-items-center rounded-control bg-slate-100 text-slate-400"><i class="size-6" data-lucide="image"></i></span>
                                                @endif
                                                <div class="min-w-0">
                                                    <p class="font-bold text-slate-950">{{ $listing->title }}</p>
                                                    <p class="mt-1 text-xs font-semibold text-brand-700">{{ $listing->category->name }}</p>
                                                    <p class="mt-2 max-w-sm truncate text-xs text-slate-500">{{ $listing->street_address }}, {{ $listing->ward->name }}, {{ $listing->ward->district->name }}</p>
                                                    @if ($listing->effective_expires_at)
                                                        <p class="mt-2 text-xs font-semibold {{ $listing->is_expired ? 'text-danger-700' : 'text-slate-600' }}">
                                                            {{ __('ui.listings.expires_at') }}: {{ $listing->effective_expires_at->format('d/m/Y H:i') }} · {{ $listing->is_expired ? __('ui.listings.expired') : __('ui.listings.active') }}
                                                        </p>
                                                    @else
                                                        <p class="mt-2 text-xs font-semibold text-slate-600">{{ __('ui.listings.expiry_pending') }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-5 font-bold text-slate-900">{{ number_format((float) $listing->monthly_rent, 0, ',', '.') }} ₫</td>
                                        <td class="px-5 py-5"><x-ui.status-badge axis="moderation" :value="$listing->currentModeration?->status ?? 'PENDING'" /></td>
                                        <td class="px-5 py-5"><x-ui.status-badge axis="occupancy" :value="$listing->occupancy_status" /></td>
                                        <td class="px-5 py-5"><x-ui.status-badge axis="visibility" :value="$listing->visibility_status" /></td>
                                        <td class="px-5 py-5">
                                            <div class="flex min-w-40 flex-col items-stretch gap-2">
                                                <a class="inline-flex min-h-10 items-center justify-center gap-2 rounded-control border border-slate-300 px-3 text-xs font-bold text-slate-700 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('landlord.listings.edit', $listing) }}"><i class="size-4" data-lucide="pencil"></i>{{ __('ui.listings.edit') }}</a>
                                                <a class="inline-flex min-h-10 items-center justify-center gap-2 rounded-control border border-slate-300 px-3 text-xs font-bold text-slate-700 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('landlord.viewing-slots.index', $listing) }}"><i class="size-4" data-lucide="calendar-days"></i>{{ __('ui.appointments.manage_slots') }}</a>
                                                @if ($listing->can_renew)
                                                    <form method="POST" action="{{ route('landlord.listings.renew', $listing) }}">
                                                        @csrf
                                                        <button class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-control bg-brand-600 px-3 text-xs font-bold text-white hover:bg-brand-700" type="submit"><i class="size-4" data-lucide="calendar-plus-2" aria-hidden="true"></i>{{ __('ui.listings.renew') }}</button>
                                                    </form>
                                                @endif
                                                <form method="POST" action="{{ route('landlord.listings.destroy', $listing) }}" onsubmit="return confirm(@js(__('ui.listings.delete_confirmation', ['title' => $listing->title])))">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-control px-3 text-xs font-bold text-danger-700 hover:bg-danger-50" type="submit"><i class="size-4" data-lucide="trash-2" aria-hidden="true"></i>{{ __('ui.listings.delete') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('landlord.listings.occupancy', $listing) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="occupancy_status" value="{{ $listing->occupancy_status === 'AVAILABLE' ? 'RENTED' : 'AVAILABLE' }}">
                                                    <button class="inline-flex min-h-10 w-full items-center justify-center rounded-control px-3 text-xs font-bold text-slate-600 hover:bg-slate-100" type="submit">{{ $listing->occupancy_status === 'AVAILABLE' ? __('ui.listings.change_to_rented') : __('ui.listings.change_to_available') }}</button>
                                                </form>
                                                @if ($listing->visibility_status !== 'SUSPENDED')
                                                    <form method="POST" action="{{ route('landlord.listings.visibility', $listing) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="visibility_status" value="{{ $listing->visibility_status === 'VISIBLE' ? 'HIDDEN' : 'VISIBLE' }}">
                                                        <button class="inline-flex min-h-10 w-full items-center justify-center rounded-control px-3 text-xs font-bold text-slate-600 hover:bg-slate-100" type="submit">{{ $listing->visibility_status === 'VISIBLE' ? __('ui.listings.change_to_hidden') : __('ui.listings.change_to_visible') }}</button>
                                                    </form>
                                                @endif
                                            </div>
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
                            <div class="flex items-start gap-3">
                                @if ($listing->coverImage)
                                    <img class="size-20 shrink-0 rounded-control object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($listing->coverImage->image_url) }}" alt="" loading="lazy">
                                @else
                                    <span class="grid size-20 shrink-0 place-items-center rounded-control bg-slate-100 text-slate-400"><i class="size-6" data-lucide="image"></i></span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <h2 class="break-words text-base font-bold text-slate-950">{{ $listing->title }}</h2>
                                    <p class="mt-1 text-xs font-semibold text-brand-700">{{ $listing->category->name }}</p>
                                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ $listing->street_address }}, {{ $listing->ward->name }}</p>
                                    @if ($listing->effective_expires_at)
                                        <p class="mt-2 text-xs font-semibold {{ $listing->is_expired ? 'text-danger-700' : 'text-slate-600' }}">
                                            {{ __('ui.listings.expires_at') }}: {{ $listing->effective_expires_at->format('d/m/Y H:i') }} · {{ $listing->is_expired ? __('ui.listings.expired') : __('ui.listings.active') }}
                                        </p>
                                    @else
                                        <p class="mt-2 text-xs font-semibold text-slate-600">{{ __('ui.listings.expiry_pending') }}</p>
                                    @endif
                                </div>
                            </div>
                            <p class="mt-4 text-lg font-bold text-slate-950">{{ number_format((float) $listing->monthly_rent, 0, ',', '.') }} ₫ <span class="text-xs font-semibold text-slate-500">/ {{ __('ui.listings.monthly_rent') }}</span></p>
                            <div class="mt-4 grid gap-2 sm:grid-cols-3">
                                <div><p class="mb-1 text-[0.65rem] font-bold uppercase tracking-wider text-slate-500">{{ __('ui.listings.moderation_short') }}</p><x-ui.status-badge axis="moderation" :value="$listing->currentModeration?->status ?? 'PENDING'" /></div>
                                <div><p class="mb-1 text-[0.65rem] font-bold uppercase tracking-wider text-slate-500">{{ __('ui.listings.occupancy_short') }}</p><x-ui.status-badge axis="occupancy" :value="$listing->occupancy_status" /></div>
                                <div><p class="mb-1 text-[0.65rem] font-bold uppercase tracking-wider text-slate-500">{{ __('ui.listings.visibility_short') }}</p><x-ui.status-badge axis="visibility" :value="$listing->visibility_status" /></div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
                                <a class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-control border border-slate-300 px-3 text-xs font-bold text-slate-700 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('landlord.listings.edit', $listing) }}"><i class="size-4" data-lucide="pencil"></i>{{ __('ui.listings.edit') }}</a>
                                <a class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-control border border-slate-300 px-3 text-xs font-bold text-slate-700 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('landlord.viewing-slots.index', $listing) }}"><i class="size-4" data-lucide="calendar-days"></i>{{ __('ui.appointments.manage_slots') }}</a>
                            </div>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                @if ($listing->can_renew)
                                    <form method="POST" action="{{ route('landlord.listings.renew', $listing) }}">
                                        @csrf
                                        <button class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-control bg-brand-600 px-3 text-xs font-bold text-white hover:bg-brand-700" type="submit"><i class="size-4" data-lucide="calendar-plus-2" aria-hidden="true"></i>{{ __('ui.listings.renew') }}</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('landlord.listings.destroy', $listing) }}" onsubmit="return confirm(@js(__('ui.listings.delete_confirmation', ['title' => $listing->title])))">
                                    @csrf
                                    @method('DELETE')
                                    <button class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-control px-3 text-xs font-bold text-danger-700 hover:bg-danger-50" type="submit"><i class="size-4" data-lucide="trash-2" aria-hidden="true"></i>{{ __('ui.listings.delete') }}</button>
                                </form>
                                <form method="POST" action="{{ route('landlord.listings.occupancy', $listing) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="occupancy_status" value="{{ $listing->occupancy_status === 'AVAILABLE' ? 'RENTED' : 'AVAILABLE' }}">
                                    <button class="inline-flex min-h-10 w-full items-center justify-center rounded-control px-3 text-xs font-bold text-slate-600 hover:bg-slate-100" type="submit">{{ $listing->occupancy_status === 'AVAILABLE' ? __('ui.listings.change_to_rented') : __('ui.listings.change_to_available') }}</button>
                                </form>
                                @if ($listing->visibility_status !== 'SUSPENDED')
                                    <form method="POST" action="{{ route('landlord.listings.visibility', $listing) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="visibility_status" value="{{ $listing->visibility_status === 'VISIBLE' ? 'HIDDEN' : 'VISIBLE' }}">
                                        <button class="inline-flex min-h-10 w-full items-center justify-center rounded-control px-3 text-xs font-bold text-slate-600 hover:bg-slate-100" type="submit">{{ $listing->visibility_status === 'VISIBLE' ? __('ui.listings.change_to_hidden') : __('ui.listings.change_to_visible') }}</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-6">{{ $listings->links() }}</div>
            @endif
        </div>
    </section>
@endsection
