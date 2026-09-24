@extends('layouts.app')

@section('title', __('ui.favorites.title'))

@section('content')
    <section class="flex-1 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <header class="max-w-3xl">
                <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.layout.wishlist') }}</p>
                <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950 sm:text-4xl">{{ __('ui.favorites.heading') }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">{{ __('ui.favorites.description') }}</p>
            </header>

            <p class="mt-5 inline-flex min-h-9 items-center gap-2 rounded-full border border-brand-100 bg-brand-50 px-3 text-sm font-bold text-brand-800" role="status">
                <i class="size-4" data-lucide="heart"></i>
                {{ __('ui.favorites.saved_count', ['count' => number_format($favorites->total())]) }}
            </p>

            @if ($favorites->total() === 0)
                <div class="spatial-card mt-6 p-8 text-center sm:p-12" role="status">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand-700"><i class="size-7" data-lucide="heart"></i></span>
                    <h2 class="mt-5 text-xl font-bold tracking-[-0.03em] text-slate-950">{{ __('ui.favorites.empty_heading') }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">{{ __('ui.favorites.empty_description') }}</p>
                    <a class="mt-6 inline-flex min-h-11 items-center justify-center rounded-control border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" href="{{ route('public.listings.index') }}">{{ __('ui.favorites.browse_rooms') }}</a>
                </div>
            @else
                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($favorites as $listing)
                        <article class="spatial-card min-w-0 overflow-hidden">
                            @if ($listing->is_publicly_eligible)
                                <a class="block overflow-hidden bg-slate-100 focus-visible:outline-offset-[-4px]" href="{{ route('public.listings.show', $listing) }}" aria-label="{{ __('ui.public_listings.open_listing', ['title' => $listing->title]) }}">
                                    @if ($listing->coverImage)
                                        <img class="aspect-[4/3] w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($listing->coverImage->image_url) }}" alt="{{ __('ui.public_listings.cover_image_alt', ['title' => $listing->title]) }}" width="640" height="480" loading="lazy">
                                    @else
                                        <span class="grid aspect-[4/3] w-full place-items-center text-slate-400" role="img" aria-label="{{ __('ui.public_listings.no_cover') }}"><i class="size-10" data-lucide="image"></i></span>
                                    @endif
                                </a>
                                <div class="p-4 sm:p-5">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <p class="text-xs font-bold text-brand-700">{{ $listing->category->name }}</p>
                                        <span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-bold text-success-700">{{ __('ui.favorites.available') }}</span>
                                    </div>
                                    <h2 class="mt-2 break-words text-lg font-bold leading-6 text-slate-950">
                                        <a class="rounded-sm hover:text-brand-800" href="{{ route('public.listings.show', $listing) }}">{{ $listing->title }}</a>
                                    </h2>
                                    <p class="mt-2 flex items-start gap-2 text-sm leading-6 text-slate-600">
                                        <i class="mt-1 size-4 shrink-0 text-brand-700" data-lucide="map-pin"></i>
                                        <span>{{ $listing->ward->district->province->name }} · {{ $listing->ward->district->name }} · {{ $listing->ward->name }}</span>
                                    </p>
                                    <p class="mt-4 text-lg font-bold text-slate-950">{{ number_format((float) $listing->monthly_rent, 0, ',', '.') }} ₫ <span class="text-xs font-semibold text-slate-500">/ {{ __('ui.listings.monthly_rent') }}</span></p>
                                    <p class="mt-1 text-sm font-medium text-slate-600">{{ number_format((float) $listing->area_m2, 2, ',', '.') }} m²</p>
                                </div>
                            @else
                                <div class="grid aspect-[4/3] place-items-center bg-slate-50 px-6 text-center" role="status">
                                    <div>
                                        <span class="mx-auto grid size-12 place-items-center rounded-full bg-warning-50 text-warning-700"><i class="size-6" data-lucide="circle-alert"></i></span>
                                        <p class="mt-3 font-bold text-slate-900">{{ __('ui.favorites.unavailable') }}</p>
                                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ __('ui.favorites.unavailable_description') }}</p>
                                    </div>
                                </div>
                            @endif

                            <div class="border-t border-line p-4 sm:p-5">
                                <form method="POST" action="{{ route('favorites.destroy', $listing) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button class="w-full" type="submit" variant="secondary">
                                        <i class="size-4" data-lucide="heart"></i>
                                        {{ __('ui.favorites.remove') }}
                                    </x-ui.button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>

                <nav class="mt-7" aria-label="{{ __('ui.favorites.pagination') }}">{{ $favorites->links() }}</nav>
            @endif
        </div>
    </section>
@endsection
