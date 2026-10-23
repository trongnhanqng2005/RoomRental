<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ __('ui.layout.meta_description') }}">
        <meta name="theme-color" content="#0f766e">
        <title>@yield('title', 'RoomRental')</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col">
        <a class="skip-link" href="#main-content">{{ __('ui.layout.skip_to_content') }}</a>

        <header class="site-header sticky top-0 z-40">
            <div class="w-full flex flex-wrap items-center justify-between gap-y-2 px-4 py-2 sm:px-6 lg:flex-nowrap lg:gap-4 lg:px-8 {{ auth()->guest() ? 'mx-auto max-w-7xl' : 'xl:px-12' }}">
                <div class="flex items-center shrink-0 w-auto lg:w-48 xl:w-56">
                    <a class="group inline-flex items-center gap-3 rounded-xl text-slate-900 transition hover:opacity-95" href="/" aria-label="{{ __('ui.layout.home_label') }}" title="{{ __('ui.layout.home_label') }}">
                        <span class="brand-glyph transition duration-200 group-hover:scale-105" aria-hidden="true">
                            <i class="size-7 text-white" data-lucide="building-2"></i>
                        </span>
                        <span class="text-xl sm:text-2xl font-black tracking-[-0.04em]">Room<span class="text-brand-600">Rental</span></span>
                    </a>
                </div>

                <button
                    class="hs-collapse-toggle inline-grid size-10 place-items-center rounded-control border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700 lg:hidden"
                    type="button"
                    aria-expanded="false"
                    aria-controls="site-navigation"
                    aria-label="{{ __('ui.layout.toggle_navigation') }}"
                    data-hs-collapse="#site-navigation"
                >
                    <i class="size-5 hs-collapse-open:hidden" data-lucide="menu"></i>
                    <i class="hidden size-5 hs-collapse-open:block" data-lucide="x"></i>
                </button>

                <div id="site-navigation" class="hs-collapse hidden w-full basis-full overflow-hidden transition-[height] duration-300 lg:flex lg:w-auto lg:basis-auto lg:flex-1 lg:items-center {{ auth()->guest() ? 'lg:justify-end' : 'lg:justify-center' }}">
                    <nav class="mt-4 flex flex-col gap-1 border-t border-slate-200 pt-4 lg:mt-0 lg:flex-row lg:items-center lg:justify-center lg:gap-1 xl:gap-1.5 lg:border-0 lg:pt-0" aria-label="{{ __('ui.layout.main_navigation') }}">
                        @if (! auth()->check() || ! auth()->user()->must_change_password)
                            <a
                                class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control px-2.5 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('public.listings.*') ? 'bg-brand-50 text-brand-800 font-bold' : 'text-slate-600' }}"
                                href="{{ route('public.listings.index') }}"
                                @if (request()->routeIs('public.listings.*')) aria-current="page" @endif
                            >
                                <i class="size-4 xl:size-[17px] shrink-0" data-lucide="search"></i>
                                {{ __('ui.layout.search_rooms') }}
                            </a>
                        @endif
                        @guest
                            <div class="flex flex-col gap-2 pt-2 border-t border-slate-100 lg:flex-row lg:items-center lg:gap-1.5 lg:pt-0 lg:border-0 lg:ml-1">
                                @if (Route::has('login'))
                                    <a class="inline-flex min-h-10 items-center justify-center rounded-control px-3 text-xs xl:text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 whitespace-nowrap" href="{{ route('login') }}">{{ __('ui.actions.login') }}</a>
                                @endif
                                <a class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control bg-brand-600 px-3.5 text-xs xl:text-sm font-bold text-white shadow-sm transition hover:bg-brand-700 active:scale-[0.98] whitespace-nowrap" href="{{ route('register') }}">
                                    {{ __('ui.actions.create_account') }}
                                    <i class="size-4" data-lucide="arrow-right"></i>
                                </a>
                            </div>
                        @else
                            @unless (auth()->user()->must_change_password)
                            @if (auth()->user()->hasRole('RENTER'))
                                <a
                                    class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control px-2.5 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('wishlist.*') ? 'bg-brand-50 text-brand-800 font-bold' : 'text-slate-600' }}"
                                    href="{{ route('wishlist.index') }}"
                                    @if (request()->routeIs('wishlist.*')) aria-current="page" @endif
                                >
                                    <i class="size-4 xl:size-[17px] shrink-0" data-lucide="heart"></i>
                                    {{ __('ui.layout.wishlist') }}
                                </a>
                                <a
                                    class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control px-2.5 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('appointments.*') ? 'bg-brand-50 text-brand-800 font-bold' : 'text-slate-600' }}"
                                    href="{{ route('appointments.index') }}"
                                    @if (request()->routeIs('appointments.*')) aria-current="page" @endif
                                >
                                    <i class="size-4 xl:size-[17px] shrink-0" data-lucide="calendar-days"></i>
                                    {{ __('ui.layout.appointments') }}
                                </a>
                            @endif
                            @if (auth()->user()->hasAnyRole('ADMIN', 'SUPER_ADMIN'))
                                <a
                                    class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control px-2.5 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('admin.dashboard') ? 'bg-brand-50 text-brand-800 font-bold' : 'text-slate-600' }}"
                                    href="{{ route('admin.dashboard') }}"
                                    @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif
                                >
                                    <i class="size-4 xl:size-[17px] shrink-0" data-lucide="layout-dashboard"></i>
                                    {{ __('ui.layout.admin_dashboard') }}
                                </a>
                                <a
                                    class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control px-2.5 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('admin.listing-moderations.*') ? 'bg-brand-50 text-brand-800 font-bold' : 'text-slate-600' }}"
                                    href="{{ route('admin.listing-moderations.index') }}"
                                    @if (request()->routeIs('admin.listing-moderations.*')) aria-current="page" @endif
                                >
                                    <i class="size-4 xl:size-[17px] shrink-0" data-lucide="shield-check"></i>
                                    {{ __('ui.layout.admin_moderation') }}
                                </a>
                                <a
                                    class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control px-2.5 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('admin.reports.*') ? 'bg-brand-50 text-brand-800 font-bold' : 'text-slate-600' }}"
                                    href="{{ route('admin.reports.index') }}"
                                    @if (request()->routeIs('admin.reports.*')) aria-current="page" @endif
                                >
                                    <i class="size-4 xl:size-[17px] shrink-0" data-lucide="flag"></i>
                                    {{ __('ui.layout.admin_reports') }}
                                </a>
                                <a
                                    class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control px-2.5 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('admin.site-management.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.categories.*') || request()->routeIs('admin.amenities.*') ? 'bg-brand-50 text-brand-800 font-bold' : 'text-slate-600' }}"
                                    href="{{ route('admin.site-management.index') }}"
                                    @if (request()->routeIs('admin.site-management.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.categories.*') || request()->routeIs('admin.amenities.*')) aria-current="page" @endif
                                >
                                    <i class="size-4 xl:size-[17px] shrink-0" data-lucide="sliders-horizontal"></i>
                                    {{ __('ui.layout.admin_site_management') }}
                                </a>
                            @endif
                            @php($unreadNotificationsCount = auth()->user()->appNotifications()->whereNull('read_at')->count())
                            <a
                                class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control px-2.5 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('notifications.*') ? 'bg-brand-50 text-brand-800 font-bold' : 'text-slate-600' }}"
                                href="{{ route('notifications.index') }}"
                                @if (request()->routeIs('notifications.*')) aria-current="page" @endif
                            >
                                <i class="size-4 xl:size-[17px] shrink-0" data-lucide="message-circle"></i>
                                {{ __('ui.layout.notifications') }}
                                @if ($unreadNotificationsCount > 0)
                                    <span class="rounded-full bg-brand-100 px-1.5 py-0.5 text-[11px] font-bold text-brand-800" aria-label="{{ __('ui.notifications.unread_count', ['count' => $unreadNotificationsCount]) }}">{{ $unreadNotificationsCount }}</span>
                                @endif
                            </a>
                            @if (auth()->user()->hasAnyRole('RENTER', 'LANDLORD'))
                                <a
                                    class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control px-2.5 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('landlord.*') && ! request()->routeIs('landlord.dashboard') && ! request()->routeIs('landlord.appointments.*') ? 'bg-brand-50 text-brand-800 font-bold' : 'text-slate-600' }}"
                                    href="{{ auth()->user()->hasRole('LANDLORD') ? route('landlord.listings.index') : route('landlord.listings.create') }}"
                                >
                                    <i class="size-4 xl:size-[17px] shrink-0" data-lucide="building-2"></i>
                                    {{ __('ui.layout.landlord_listings') }}
                                </a>
                            @endif
                            @if (auth()->user()->hasRole('LANDLORD'))
                                <a
                                    class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control px-2.5 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('landlord.dashboard') ? 'bg-brand-50 text-brand-800 font-bold' : 'text-slate-600' }}"
                                    href="{{ route('landlord.dashboard') }}"
                                    @if (request()->routeIs('landlord.dashboard')) aria-current="page" @endif
                                >
                                    <i class="size-4 xl:size-[17px] shrink-0" data-lucide="layout-dashboard"></i>
                                    {{ __('ui.layout.landlord_dashboard') }}
                                </a>
                                <a
                                    class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control px-2.5 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('landlord.appointments.*') ? 'bg-brand-50 text-brand-800 font-bold' : 'text-slate-600' }}"
                                    href="{{ route('landlord.appointments.index') }}"
                                    @if (request()->routeIs('landlord.appointments.*')) aria-current="page" @endif
                                >
                                    <i class="size-4 xl:size-[17px] shrink-0" data-lucide="calendar-check-2"></i>
                                    {{ __('ui.layout.landlord_appointments') }}
                                </a>
                            @endif
                            <a
                                class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-control px-2.5 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('profile.show') ? 'bg-brand-50 text-brand-800 font-bold' : 'text-slate-600' }}"
                                href="{{ route('profile.show') }}"
                                @if (request()->routeIs('profile.show')) aria-current="page" @endif
                            >
                                <i class="size-4 xl:size-[17px] shrink-0" data-lucide="user-round"></i>
                                {{ __('ui.layout.profile') }}
                            </a>
                            <div class="pt-2 border-t border-slate-100 lg:border-0 lg:pt-0 lg:ml-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-ui.button class="w-full lg:w-auto min-h-10 px-3 py-1.5 text-xs xl:text-sm font-semibold whitespace-nowrap" type="submit" variant="secondary">{{ __('ui.actions.logout') }}</x-ui.button>
                                </form>
                            </div>
                            @endunless
                        @endguest
                    </nav>
                </div>
                @auth
                    <div class="hidden lg:block shrink-0 invisible pointer-events-none lg:w-48 xl:w-56" aria-hidden="true"></div>
                @endauth
            </div>
        </header>

        <main id="main-content" class="flex flex-1 flex-col">
            @yield('content')
        </main>

        <footer class="border-t border-line bg-white/75 py-5">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <p class="font-semibold text-slate-600">RoomRental</p>
                <p>{{ __('ui.layout.tagline') }}</p>
            </div>
        </footer>
    </body>
</html>
