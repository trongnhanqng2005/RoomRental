@extends('layouts.app')

@section('title', __('ui.catalogs.amenities_title'))

@section('content')
    <section class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <header class="mb-7">
            <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.user_management.eyebrow') }}</p>
            <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950">{{ __('ui.catalogs.amenities_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">{{ __('ui.catalogs.amenities_description') }}</p>
        </header>

        @if (session('status'))
            <p class="mb-6 rounded-control border border-success-100 bg-success-50 p-4 text-sm font-semibold text-success-700" role="status">{{ session('status') }}</p>
        @endif

        <form class="mb-8 space-y-4 rounded-panel border border-line bg-white p-4 sm:p-6" method="POST" action="{{ route('admin.amenities.store') }}">
            @csrf
            <h2 class="text-lg font-bold text-slate-950">{{ __('ui.catalogs.create_heading') }}</h2>
            <x-ui.error-summary :fields="['name' => __('ui.catalogs.name'), 'description' => __('ui.catalogs.description')]" id="amenity-create-errors" />
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-800" for="name">{{ __('ui.catalogs.name') }}</label>
                    <input class="field-control" id="name" name="name" maxlength="100" value="{{ old('name') }}" required @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
                    @error('name') <p class="mt-2 text-sm font-medium text-danger-700" id="name-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-800" for="description">{{ __('ui.catalogs.description') }} <span class="font-medium text-slate-400">({{ __('ui.forms.optional') }})</span></label>
                    <input class="field-control" id="description" name="description" maxlength="500" value="{{ old('description') }}" @if ($errors->has('description')) aria-invalid="true" aria-describedby="description-error" @endif>
                    @error('description') <p class="mt-2 text-sm font-medium text-danger-700" id="description-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <x-ui.button type="submit" variant="primary"><i class="size-4" data-lucide="plus" aria-hidden="true"></i>{{ __('ui.catalogs.create') }}</x-ui.button>
        </form>

        <div class="space-y-3">
            @forelse ($amenities as $amenity)
                <article class="rounded-panel border border-line bg-white p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="break-words text-lg font-bold text-slate-950">{{ $amenity->name }}</h2>
                            @if ($amenity->description)
                                <p class="mt-1 break-words text-sm leading-6 text-slate-600">{{ $amenity->description }}</p>
                            @endif
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $amenity->is_active ? 'bg-success-50 text-success-700' : 'bg-slate-100 text-slate-700' }}">{{ $amenity->is_active ? __('ui.catalogs.active') : __('ui.catalogs.hidden') }}</span>
                    </div>
                    <p class="mt-3 text-sm text-slate-600">{{ __('ui.catalogs.usage_count') }}: <span class="font-bold text-slate-900">{{ $amenity->listings_count }}</span></p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a class="inline-flex min-h-11 items-center justify-center rounded-control border border-line px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-brand-600" href="{{ route('admin.amenities.edit', $amenity) }}">{{ __('ui.catalogs.edit') }}</a>
                        @if ($amenity->is_active)
                            <form method="POST" action="{{ route('admin.amenities.hide', $amenity) }}" onsubmit="return confirm(@js(__('ui.catalogs.hide_confirmation')))" data-submit-once>
                                @csrf
                                @method('PATCH')
                                <button class="inline-flex min-h-11 items-center justify-center rounded-control border border-danger-200 px-4 text-sm font-bold text-danger-700 hover:bg-danger-50 focus-visible:outline-2 focus-visible:outline-danger-600" type="submit">{{ __('ui.catalogs.hide') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.amenities.restore', $amenity) }}" onsubmit="return confirm(@js(__('ui.catalogs.restore_confirmation')))" data-submit-once>
                                @csrf
                                @method('PATCH')
                                <button class="inline-flex min-h-11 items-center justify-center rounded-control border border-brand-200 px-4 text-sm font-bold text-brand-700 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-brand-600" type="submit">{{ __('ui.catalogs.restore') }}</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-panel border border-line bg-white px-6 py-12 text-center">
                    <h2 class="text-xl font-bold text-slate-950">{{ __('ui.catalogs.empty') }}</h2>
                </div>
            @endforelse
        </div>
    </section>
@endsection
