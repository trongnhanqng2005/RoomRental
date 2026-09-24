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

            <details class="spatial-card mt-7 p-4 sm:p-6" open>
                <summary class="cursor-pointer text-lg font-bold text-slate-950 focus-visible:rounded-control">{{ __('ui.public_listings.filters_heading') }}</summary>
                <form class="mt-5" method="GET" action="{{ route('public.listings.index') }}" data-public-location-filters>
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

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <x-ui.input
                            name="q"
                            :label="__('ui.public_listings.keyword')"
                            type="search"
                            :value="request('q')"
                            :placeholder="__('ui.public_listings.keyword_placeholder')"
                            icon="search"
                            maxlength="255"
                        />

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
                                    <label class="flex min-h-11 items-center gap-3 rounded-control border border-line bg-white px-3 py-2 text-sm font-semibold text-slate-700 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-800">
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

                    <div class="mt-5 flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center">
                        <x-ui.button type="submit"><i class="size-4" data-lucide="search"></i>{{ __('ui.public_listings.search_action') }}</x-ui.button>
                        <a class="inline-flex min-h-11 items-center justify-center rounded-control border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('public.listings.index') }}">{{ __('ui.public_listings.clear_filters') }}</a>
                    </div>
                </form>
            </details>

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
                        <article class="spatial-card interactive-lift min-w-0 overflow-hidden">
                            <a class="block overflow-hidden bg-slate-100 focus-visible:outline-offset-[-4px]" href="{{ route('public.listings.show', $listing) }}" aria-label="{{ __('ui.public_listings.open_listing', ['title' => $listing->title]) }}">
                                @if ($listing->coverImage)
                                    <img class="aspect-[4/3] w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($listing->coverImage->image_url) }}" alt="{{ __('ui.public_listings.cover_image_alt', ['title' => $listing->title]) }}" width="640" height="480" loading="lazy">
                                @else
                                    <span class="grid aspect-[4/3] w-full place-items-center text-slate-400" role="img" aria-label="{{ __('ui.public_listings.no_cover') }}"><i class="size-10" data-lucide="image"></i></span>
                                @endif
                            </a>
                            <div class="p-4 sm:p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-xs font-bold text-brand-700">{{ $listing->category->name }}</p>
                                    <span class="shrink-0 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-800">{{ __('ui.listings.'.match ($listing->gender_requirement) { 'MALE' => 'male_only', 'FEMALE' => 'female_only', default => 'any_gender' }) }}</span>
                                </div>
                                <h3 class="mt-2 break-words text-lg font-bold leading-6 text-slate-950">
                                    <a class="rounded-sm hover:text-brand-800" href="{{ route('public.listings.show', $listing) }}">{{ $listing->title }}</a>
                                </h3>
                                <p class="mt-2 flex items-start gap-2 text-sm leading-6 text-slate-600">
                                    <i class="mt-1 size-4 shrink-0 text-brand-700" data-lucide="map-pin"></i>
                                    <span>{{ $listing->ward->district->province->name }} · {{ $listing->ward->district->name }} · {{ $listing->ward->name }}</span>
                                </p>
                                <p class="mt-4 text-lg font-bold text-slate-950">{{ number_format((float) $listing->monthly_rent, 0, ',', '.') }} ₫ <span class="text-xs font-semibold text-slate-500">/ {{ __('ui.listings.monthly_rent') }}</span></p>
                                <p class="mt-1 text-sm font-medium text-slate-600">{{ number_format((float) $listing->area_m2, 2, ',', '.') }} m²</p>
                                @if ($listing->amenities->isNotEmpty())
                                    <ul class="mt-3 flex flex-wrap gap-2" aria-label="{{ __('ui.public_listings.card_amenities') }}">
                                        @foreach ($listing->amenities as $amenity)
                                            <li class="rounded-full border border-line bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $amenity->name }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
                <nav class="mt-7" aria-label="{{ __('ui.public_listings.pagination') }}">{{ $listings->links() }}</nav>
            @endif
        </div>
    </section>
@endsection
