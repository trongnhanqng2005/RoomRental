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
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <a class="group inline-flex items-center gap-3 rounded-lg text-slate-900" href="/" aria-label="{{ __('ui.layout.home_label') }}">
                    <span class="brand-glyph transition duration-200 group-hover:scale-[1.03]" aria-hidden="true">
                        <i class="size-5" data-lucide="building-2"></i>
                    </span>
                    <span class="text-[1.05rem] font-bold tracking-[-0.035em]">Room<span class="text-brand-600">Rental</span></span>
                </a>

                <button
                    class="hs-collapse-toggle inline-grid size-11 place-items-center rounded-control border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700 lg:hidden"
                    type="button"
                    aria-expanded="false"
                    aria-controls="site-navigation"
                    aria-label="{{ __('ui.layout.toggle_navigation') }}"
                    data-hs-collapse="#site-navigation"
                >
                    <i class="size-5 hs-collapse-open:hidden" data-lucide="menu"></i>
                    <i class="hidden size-5 hs-collapse-open:block" data-lucide="x"></i>
                </button>

                <div id="site-navigation" class="hs-collapse hidden w-full basis-full overflow-hidden transition-[height] duration-300 lg:block lg:w-auto lg:basis-auto">
                    <nav class="mt-4 flex flex-col gap-2 border-t border-slate-200 pt-4 lg:mt-0 lg:flex-row lg:items-center lg:border-0 lg:pt-0" aria-label="{{ __('ui.layout.main_navigation') }}">
                        @guest
                            @if (Route::has('login'))
                                <a class="inline-flex min-h-11 items-center justify-center rounded-control px-4 text-sm font-bold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900" href="{{ route('login') }}">{{ __('ui.actions.login') }}</a>
                            @endif
                            <a class="inline-flex min-h-11 items-center justify-center gap-2 rounded-control bg-brand-600 px-4 text-sm font-bold text-white shadow-[0_10px_24px_-12px_rgb(15_118_110_/_0.9)] transition hover:bg-brand-700 active:scale-[0.98]" href="{{ route('register') }}">
                                {{ __('ui.actions.create_account') }}
                                <i class="size-4" data-lucide="arrow-right"></i>
                            </a>
                        @else
                            @if (auth()->user()->hasAnyRole('ADMIN', 'SUPER_ADMIN'))
                                <a
                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-control px-3 py-2 text-sm font-bold transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('admin.listing-moderations.*') ? 'bg-brand-50 text-brand-800' : 'text-slate-600' }}"
                                    href="{{ route('admin.listing-moderations.index') }}"
                                    @if (request()->routeIs('admin.listing-moderations.*')) aria-current="page" @endif
                                >
                                    <i class="size-[18px]" data-lucide="shield-check"></i>
                                    {{ __('ui.layout.admin_moderation') }}
                                </a>
                            @endif
                            @php($unreadNotificationsCount = auth()->user()->appNotifications()->whereNull('read_at')->count())
                            <a
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-control px-3 py-2 text-sm font-bold transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('notifications.*') ? 'bg-brand-50 text-brand-800' : 'text-slate-600' }}"
                                href="{{ route('notifications.index') }}"
                                @if (request()->routeIs('notifications.*')) aria-current="page" @endif
                            >
                                <i class="size-[18px]" data-lucide="message-circle"></i>
                                {{ __('ui.layout.notifications') }}
                                @if ($unreadNotificationsCount > 0)
                                    <span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-800" aria-label="{{ __('ui.notifications.unread_count', ['count' => $unreadNotificationsCount]) }}">{{ $unreadNotificationsCount }}</span>
                                @endif
                            </a>
                            @if (auth()->user()->hasAnyRole('RENTER', 'LANDLORD'))
                                <a
                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-control px-3 py-2 text-sm font-bold transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('landlord.*') ? 'bg-brand-50 text-brand-800' : 'text-slate-600' }}"
                                    href="{{ auth()->user()->hasRole('LANDLORD') ? route('landlord.listings.index') : route('landlord.listings.create') }}"
                                >
                                    <i class="size-[18px]" data-lucide="building-2"></i>
                                    {{ __('ui.layout.landlord_listings') }}
                                </a>
                            @endif
                            <a
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-control px-3 py-2 text-sm font-bold transition hover:bg-brand-50 hover:text-brand-800 {{ request()->routeIs('profile.show') ? 'bg-brand-50 text-brand-800' : 'text-slate-600' }}"
                                href="{{ route('profile.show') }}"
                                @if (request()->routeIs('profile.show')) aria-current="page" @endif
                            >
                                <i class="size-[18px]" data-lucide="user-round"></i>
                                {{ __('ui.layout.profile') }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-ui.button class="w-full lg:w-auto" type="submit" variant="secondary">{{ __('ui.actions.logout') }}</x-ui.button>
                            </form>
                        @endguest
                    </nav>
                </div>
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
