@extends('layouts.app')

@php
    $activeTab = $activeTab ?? 'users';
    $tabTitles = [
        'users' => __('ui.user_management.title'),
        'categories' => __('ui.catalogs.categories_title'),
        'amenities' => __('ui.catalogs.amenities_title'),
    ];
@endphp

@section('title', $tabTitles[$activeTab] ?? __('ui.layout.admin_site_management'))

@section('content')
    <section class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <header class="mb-7">
            <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.user_management.eyebrow') }}</p>
            <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950">{{ __('ui.layout.admin_site_management') }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">Quản lý người dùng, danh mục phòng và các tiện nghi phòng trọ trong hệ thống.</p>
        </header>

        <!-- 3 Tabs Navigation -->
        <div class="mb-8 border-b border-slate-200">
            <nav class="-mb-px flex flex-wrap gap-2 sm:gap-6" aria-label="Các tab Quản lý trang">
                <a
                    href="{{ route('admin.site-management.index', ['tab' => 'users']) }}"
                    class="inline-flex items-center gap-2 border-b-2 py-3 px-3 text-sm sm:text-base font-bold transition {{ $activeTab === 'users' ? 'border-brand-600 text-brand-700 font-extrabold' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' }}"
                    @if ($activeTab === 'users') aria-current="page" @endif
                >
                    <i class="size-4.5" data-lucide="users"></i>
                    {{ __('ui.user_management.heading') }}
                </a>

                <a
                    href="{{ route('admin.site-management.index', ['tab' => 'categories']) }}"
                    class="inline-flex items-center gap-2 border-b-2 py-3 px-3 text-sm sm:text-base font-bold transition {{ $activeTab === 'categories' ? 'border-brand-600 text-brand-700 font-extrabold' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' }}"
                    @if ($activeTab === 'categories') aria-current="page" @endif
                >
                    <i class="size-4.5" data-lucide="list"></i>
                    {{ __('ui.catalogs.categories_heading') }}
                </a>

                <a
                    href="{{ route('admin.site-management.index', ['tab' => 'amenities']) }}"
                    class="inline-flex items-center gap-2 border-b-2 py-3 px-3 text-sm sm:text-base font-bold transition {{ $activeTab === 'amenities' ? 'border-brand-600 text-brand-700 font-extrabold' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' }}"
                    @if ($activeTab === 'amenities') aria-current="page" @endif
                >
                    <i class="size-4.5" data-lucide="sparkles"></i>
                    {{ __('ui.catalogs.amenities_heading') }}
                </a>
            </nav>
        </div>

        @if ($activeTab === 'users')
            @include('admin.site-management.tabs.users')
        @elseif ($activeTab === 'categories')
            @include('admin.site-management.tabs.categories')
        @elseif ($activeTab === 'amenities')
            @include('admin.site-management.tabs.amenities')
        @endif
    </section>
@endsection
