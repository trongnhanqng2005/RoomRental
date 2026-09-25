@extends('layouts.app')

@section('title', __('ui.catalogs.categories_title'))

@section('content')
    <section class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <header class="mb-7">
            <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.catalogs.categories_heading') }}</p>
            <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950">{{ __('ui.catalogs.edit_heading') }}</h1>
        </header>
        <form class="space-y-5 rounded-panel border border-line bg-white p-4 sm:p-6" method="POST" action="{{ route('admin.categories.update', $category) }}">
            @csrf
            @method('PUT')
            <x-ui.error-summary :fields="['name' => __('ui.catalogs.name'), 'description' => __('ui.catalogs.description')]" id="category-edit-errors" />
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-800" for="name">{{ __('ui.catalogs.name') }}</label>
                <input class="field-control" id="name" name="name" maxlength="100" value="{{ old('name', $category->name) }}" required @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
                @error('name') <p class="mt-2 text-sm font-medium text-danger-700" id="name-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-800" for="description">{{ __('ui.catalogs.description') }}</label>
                <textarea class="field-control min-h-28" id="description" name="description" maxlength="500" @if ($errors->has('description')) aria-invalid="true" aria-describedby="description-error" @endif>{{ old('description', $category->description) }}</textarea>
                @error('description') <p class="mt-2 text-sm font-medium text-danger-700" id="description-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-line pt-5 sm:flex-row sm:justify-between">
                <a class="inline-flex min-h-11 items-center justify-center rounded-control border border-line px-4 text-sm font-bold text-slate-700 hover:bg-slate-50" href="{{ route('admin.categories.index') }}">{{ __('ui.catalogs.back') }}</a>
                <x-ui.button type="submit" variant="primary">{{ __('ui.catalogs.save') }}</x-ui.button>
            </div>
        </form>
    </section>
@endsection
