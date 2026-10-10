@extends('layouts.app')

@section('title', __('ui.public_listings.title'))

@section('content')
    <section class="flex-1 py-8 sm:py-10 lg:py-12">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <header class="max-w-3xl">
                <p class="mb-3 text-sm font-bold text-brand-700">{{ __('ui.public_listings.eyebrow') }}</p>
                <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950 sm:text-4xl">{{ __('ui.public_listings.heading') }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.public_listings.description') }}</p>
            </header>

            @php
                $advancedFilters = [
                    'province_id' => request('province_id'),
                    'district_id' => request('district_id'),
                    'ward_id' => request('ward_id'),
                    'min_price' => request('min_price'),
                    'max_price' => request('max_price'),
                    'min_area' => request('min_area'),
                    'max_area' => request('max_area'),
                    'gender_requirement' => request('gender_requirement'),
                    'amenity_ids' => request('amenity_ids'),
                ];
                $activeFilterCount = collect($advancedFilters)->filter(fn ($v) => !empty($v))->count();
                if (request('sort') && request('sort') !== 'newest') {
                    $activeFilterCount++;
                }
                $hasActiveFilters = $activeFilterCount > 0 || $errors->isNotEmpty();
            @endphp

            <div class="spatial-card mt-7 p-4 sm:p-5">
                <form method="GET" action="{{ route('public.listings.index') }}" data-public-location-filters id="public-listing-search-form">
                    <x-ui.error-summary
                        :fields="[
                            'q' => __('ui.public_listings.keyword'),
                            'province_id' => __('ui.listings.province'),
                            'district_id' => __('ui.listings.district'),
                            'ward_id' => __('ui.listings.ward'),
                            'min_price' => __('ui.public_listings.min_price'),
                            'max_price' => __('ui.public_listings.max_price'),
                            'min_area' => __('ui.public_listings.min_area'),
                            'max_area' => __('ui.public_listings.max_area'),
                            'amenity_ids' => __('ui.listings.amenities'),
                            'gender_requirement' => __('ui.listings.gender_requirement'),
                            'sort' => __('ui.public_listings.sort'),
                        ]"
                        id="public-listing-search-errors"
                    />

                    {{-- Khung tìm kiếm chính luôn hiển thị --}}
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative flex-1">
                            <label class="sr-only" for="public-keyword-input">{{ __('ui.public_listings.keyword') }}</label>
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <i class="size-5" data-lucide="search"></i>
                            </div>
                            <input
                                class="field-control pl-11 pr-4"
                                id="public-keyword-input"
                                name="q"
                                type="search"
                                value="{{ request('q') }}"
                                placeholder="{{ __('ui.public_listings.keyword_placeholder') }}"
                                maxlength="255"
                            />
                        </div>

                        <div class="flex items-center gap-2.5 shrink-0">
                            <button
                                type="button"
                                id="filter-toggle-button"
                                data-filter-toggle
                                aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}"
                                aria-controls="advanced-filters-panel"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-control border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800 cursor-pointer {{ $hasActiveFilters ? 'border-brand-500 bg-brand-50 text-brand-800' : '' }}"
                            >
                                <i class="size-4.5 text-brand-700" data-lucide="list-filter"></i>
                                <span>Bộ lọc</span>
                                @if ($activeFilterCount > 0)
                                    <span class="inline-flex size-5 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">
                                        {{ $activeFilterCount }}
                                    </span>
                                @endif
                                <i class="size-4 text-slate-500 transition-transform duration-200 {{ $hasActiveFilters ? 'rotate-180' : '' }}" data-filter-chevron data-lucide="chevron-down"></i>
                            </button>

                            <x-ui.button type="submit" class="min-h-12 px-6">
                                <i class="size-4" data-lucide="search"></i>
                                <span>{{ __('ui.public_listings.search_action') }}</span>
                            </x-ui.button>
                        </div>
                    </div>

                    {{-- Khung mở rộng: chỉ hiển thị khi ấn vào nút Bộ lọc --}}
                    <div
                        id="advanced-filters-panel"
                        data-filter-panel
                        class="{{ $hasActiveFilters ? '' : 'hidden' }} mt-5 border-t border-line/70 pt-5"
                    >
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <label class="mb-2 block text-sm font-bold text-slate-800" for="province_id">{{ __('ui.listings.province') }}</label>
                                <select class="field-control" id="province_id" name="province_id" data-location="province" @if ($errors->has('province_id')) aria-invalid="true" @endif>
                                    <option value="">{{ __('ui.public_listings.all_provinces') }}</option>
                                    @foreach ($provinces as $province)
                                        <option value="{{ $province->id }}" @selected((string) old('province_id', request('province_id')) === (string) $province->id)>{{ $province->name }}</option>
                                    @endforeach
                                </select>
                                @error('province_id') <p class="mt-2 text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-bold text-slate-800" for="district_id">{{ __('ui.listings.district') }}</label>
                                <select class="field-control" id="district_id" name="district_id" data-location="district" @disabled(! request('province_id')) @if ($errors->has('district_id')) aria-invalid="true" @endif>
                                    <option value="">{{ __('ui.public_listings.all_districts') }}</option>
                                    @foreach ($provinces as $province)
                                        @foreach ($province->districts as $district)
                                            <option value="{{ $district->id }}" data-location-parent="{{ $province->id }}" @selected((string) old('district_id', request('district_id')) === (string) $district->id)>{{ $district->name }}</option>
                                        @endforeach
                                    @endforeach
                                </select>
                                @error('district_id') <p class="mt-2 text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-bold text-slate-800" for="ward_id">{{ __('ui.listings.ward') }}</label>
                                <select class="field-control" id="ward_id" name="ward_id" data-location="ward" @disabled(! request('district_id')) @if ($errors->has('ward_id')) aria-invalid="true" @endif>
                                    <option value="">{{ __('ui.public_listings.all_wards') }}</option>
                                    @foreach ($provinces as $province)
                                        @foreach ($province->districts as $district)
                                            @foreach ($district->wards as $ward)
                                                <option value="{{ $ward->id }}" data-location-parent="{{ $district->id }}" @selected((string) old('ward_id', request('ward_id')) === (string) $ward->id)>{{ $ward->name }}</option>
                                            @endforeach
                                        @endforeach
                                    @endforeach
                                </select>
                                @error('ward_id') <p class="mt-2 text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
                            </div>

                            <x-ui.input name="min_price" :label="__('ui.public_listings.min_price')" type="number" :value="request('min_price')" inputmode="decimal" min="0" step="0.01" />
                            <x-ui.input name="max_price" :label="__('ui.public_listings.max_price')" type="number" :value="request('max_price')" inputmode="decimal" min="0" step="0.01" />
                            <x-ui.input name="min_area" :label="__('ui.public_listings.min_area')" type="number" :value="request('min_area')" inputmode="decimal" min="0" step="0.01" />
                            <x-ui.input name="max_area" :label="__('ui.public_listings.max_area')" type="number" :value="request('max_area')" inputmode="decimal" min="0" step="0.01" />

                            <x-ui.select
                                name="gender_requirement"
                                :label="__('ui.listings.gender_requirement')"
                                :options="[
                                    'ANY' => __('ui.public_listings.gender_any'),
                                    'MALE' => __('ui.public_listings.gender_male'),
                                    'FEMALE' => __('ui.public_listings.gender_female'),
                                ]"
                                :value="request('gender_requirement')"
                                :placeholder="__('ui.public_listings.gender_all')"
                            />

                            <x-ui.select
                                name="sort"
                                :label="__('ui.public_listings.sort')"
                                :options="[
                                    'newest' => __('ui.public_listings.sort_newest'),
                                    'price_asc' => __('ui.public_listings.sort_price_asc'),
                                    'price_desc' => __('ui.public_listings.sort_price_desc'),
                                    'area_asc' => __('ui.public_listings.sort_area_asc'),
                                    'area_desc' => __('ui.public_listings.sort_area_desc'),
                                    'most_viewed' => __('ui.public_listings.sort_most_viewed'),
                                ]"
                                :value="request('sort', 'newest')"
                            />
                        </div>

                        <fieldset class="mt-5">
                            <legend class="text-sm font-bold text-slate-800">{{ __('ui.listings.amenities') }}</legend>
                            @if ($amenities->isEmpty())
                                <p class="mt-2 text-sm text-slate-500">{{ __('ui.public_listings.no_amenities') }}</p>
                            @else
                                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                    @foreach ($amenities as $amenity)
                                        <label class="flex min-h-11 items-center gap-3 rounded-control border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-800 cursor-pointer">
                                            <input class="size-4 shrink-0 accent-brand-600" type="checkbox" name="amenity_ids[]" value="{{ $amenity->id }}" @checked(in_array((string) $amenity->id, array_map('strval', (array) old('amenity_ids', request('amenity_ids', []))), true))>
                                            {{ $amenity->name }}
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            @error('amenity_ids') <p class="mt-2 text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
                            @foreach ($errors->get('amenity_ids.*') as $message)
                                <p class="mt-2 text-sm font-medium text-danger-700">{{ $message }}</p>
                            @endforeach
                        </fieldset>

                        <div class="mt-5 flex flex-col gap-3 border-t border-line/70 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <a class="inline-flex min-h-11 items-center justify-center rounded-control border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('public.listings.index') }}">
                                <i class="size-4 mr-1.5" data-lucide="x"></i>
                                {{ __('ui.public_listings.clear_filters') }}
                            </a>
                            <div class="flex items-center gap-2">
                                <button type="button" data-filter-close class="inline-flex min-h-11 items-center justify-center rounded-control border border-transparent px-4 py-2.5 text-sm font-semibold text-slate-600 hover:text-slate-900 cursor-pointer">
                                    Thu gọn
                                </button>
                                <x-ui.button type="submit">
                                    <i class="size-4" data-lucide="search"></i>
                                    Áp dụng bộ lọc
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="mt-7 flex flex-wrap items-baseline justify-between gap-2" aria-live="polite">
                <h2 class="text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.public_listings.results_heading') }}</h2>
                <p class="text-sm font-semibold text-slate-600">{{ __('ui.public_listings.results_count', ['count' => number_format($listings->total())]) }}</p>
            </div>

            @if ($listings->isEmpty())
                <div class="spatial-card mt-4 p-8 text-center sm:p-12" role="status">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand-700"><i class="size-7" data-lucide="building-2"></i></span>
                    <h2 class="mt-5 text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.public_listings.empty_heading') }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">{{ __('ui.public_listings.empty_description') }}</p>
                    <a class="mt-6 inline-flex min-h-11 items-center justify-center rounded-control border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('public.listings.index') }}">{{ __('ui.public_listings.clear_filters') }}</a>
                </div>
            @else
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($listings as $listing)
                        <article class="spatial-card interactive-lift flex h-full flex-col min-w-0 overflow-hidden">
                            <a class="block overflow-hidden bg-slate-100 focus-visible:outline-offset-[-4px]" href="{{ route('public.listings.show', $listing) }}" aria-label="{{ __('ui.public_listings.open_listing', ['title' => strip_tags($listing->title)]) }}">
                                @if ($listing->coverImage)
                                    <img class="aspect-[4/3] w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($listing->coverImage->image_url) }}" alt="{{ __('ui.public_listings.cover_image_alt', ['title' => strip_tags($listing->title)]) }}" width="640" height="480" loading="lazy">
                                @else
                                    <span class="grid aspect-[4/3] w-full place-items-center text-slate-400" role="img" aria-label="{{ __('ui.public_listings.no_cover') }}"><i class="size-10" data-lucide="image"></i></span>
                                @endif
                            </a>
                            <div class="flex flex-1 flex-col p-4 sm:p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs font-bold text-brand-700">{{ $listing->category->name }}</p>
                                    <span class="shrink-0 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-800">{{ __('ui.listings.'.match ($listing->gender_requirement) { 'MALE' => 'male_only', 'FEMALE' => 'female_only', default => 'any_gender' }) }}</span>
                                </div>
                                <h3 class="mt-2 line-clamp-2 min-h-[3rem] text-lg font-bold leading-6 text-slate-950">
                                    <a class="rounded-sm hover:text-brand-800" href="{{ route('public.listings.show', $listing) }}" title="{{ strip_tags($listing->title) }}">{!! \App\Support\HtmlSanitizer::clean($listing->title) !!}</a>
                                </h3>
                                <p class="mt-2 flex items-center gap-2 text-sm leading-6 text-slate-600 min-h-[1.5rem]">
                                    <i class="size-4 shrink-0 text-brand-700" data-lucide="map-pin"></i>
                                    <span class="truncate" title="{{ $listing->ward->district->province->name }} · {{ $listing->ward->district->name }} · {{ $listing->ward->name }}">{{ $listing->ward->district->province->name }} · {{ $listing->ward->district->name }} · {{ $listing->ward->name }}</span>
                                </p>
                                <p class="mt-3 text-lg font-bold text-slate-950">{{ number_format((float) $listing->monthly_rent, 0, ',', '.') }} ₫ <span class="text-xs font-semibold text-slate-500">/ {{ __('ui.listings.monthly_rent') }}</span></p>
                                <p class="mt-1 text-sm font-medium text-slate-600">{{ number_format((float) $listing->area_m2, 2, ',', '.') }} m²</p>

                                <div class="mt-3 flex h-7 items-center gap-1.5 overflow-hidden" aria-label="{{ __('ui.public_listings.card_amenities') }}">
                                    @if ($listing->amenities->isNotEmpty())
                                        @foreach ($listing->amenities->take(3) as $amenity)
                                            <span class="inline-flex shrink-0 items-center rounded-full border border-line bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $amenity->name }}</span>
                                        @endforeach
                                        @if ($listing->amenities->count() > 3)
                                            <span class="inline-flex shrink-0 items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600" title="{{ $listing->amenities->slice(3)->pluck('name')->join(', ') }}">+{{ $listing->amenities->count() - 3 }}</span>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-dashed border-slate-200 bg-slate-50/50 px-2.5 py-0.5 text-xs font-medium text-slate-400">Tiện nghi cơ bản</span>
                                    @endif
                                </div>

                                <div class="mt-auto pt-4">
                                    @if ($canFavorite)
                                        @php($isFavorited = in_array((int) $listing->id, $favoritedListingIds, true))
                                        <form method="POST" action="{{ route($isFavorited ? 'favorites.destroy' : 'favorites.store', $listing) }}">
                                            @csrf
                                            @if ($isFavorited) @method('DELETE') @endif
                                            <x-ui.button class="w-full" type="submit" variant="secondary" :aria-pressed="$isFavorited ? 'true' : 'false'">
                                                <i class="size-4 {{ $isFavorited ? 'fill-current text-danger-700' : 'text-slate-500' }}" data-lucide="heart"></i>
                                                {{ $isFavorited ? __('ui.favorites.remove') : __('ui.favorites.save') }}
                                            </x-ui.button>
                                        </form>
                                    @elseif (! auth()->check())
                                        <a class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-control border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('login') }}">
                                            <i class="size-4" data-lucide="heart"></i>
                                            {{ __('ui.favorites.login_to_save') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                <nav class="mt-7" aria-label="{{ __('ui.public_listings.pagination') }}">{{ $listings->links() }}</nav>
            @endif
        </div>
    </section>
@endsection
