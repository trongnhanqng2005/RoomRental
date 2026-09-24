@extends('layouts.app')

@section('title', __('ui.notifications.title'))

@section('content')
    <section class="flex-1 py-8 sm:py-10">
        <div class="mx-auto w-full max-w-5xl px-4 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.layout.notifications') }}</p>
                    <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950">{{ __('ui.notifications.heading') }}</h1>
                    <p class="mt-3 text-sm leading-7 text-slate-600">{{ __('ui.notifications.description') }}</p>
                </div>
                <p class="inline-flex min-h-9 items-center gap-2 self-start rounded-full border border-brand-100 bg-brand-50 px-3 text-sm font-bold text-brand-800 sm:self-auto" role="status">
                    <i class="size-4" data-lucide="message-circle"></i>
                    {{ __('ui.notifications.unread_count_short', ['count' => $unreadCount]) }}
                </p>
            </header>

            @if ($notifications->isEmpty())
                <div class="spatial-card mt-7 p-8 text-center sm:p-12" role="status">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand-700"><i class="size-7" data-lucide="message-circle"></i></span>
                    <h2 class="mt-5 text-xl font-bold text-slate-950">{{ __('ui.notifications.empty_heading') }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">{{ __('ui.notifications.empty_description') }}</p>
                </div>
            @else
                <ol class="mt-7 space-y-3">
                    @foreach ($notifications as $notification)
                        <li class="spatial-card flex flex-col gap-4 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="mt-0.5 grid size-10 shrink-0 place-items-center rounded-control {{ $notification->read_at ? 'bg-slate-100 text-slate-600' : 'bg-brand-50 text-brand-700' }}" aria-hidden="true">
                                    <i class="size-5" data-lucide="{{ $notification->notification_type === 'LISTING_REJECTED' ? 'circle-alert' : 'check' }}"></i>
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="break-words font-bold text-slate-950">{{ $notification->title }}</h2>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $notification->read_at ? 'bg-slate-100 text-slate-700' : 'bg-warning-50 text-warning-700' }}">{{ $notification->read_at ? __('ui.notifications.read') : __('ui.notifications.unread') }}</span>
                                    </div>
                                    <p class="mt-2 break-words text-sm leading-6 text-slate-700">{{ $notification->message }}</p>
                                    <p class="mt-2 text-xs text-slate-500">{{ $notification->created_at->format('d/m/Y H:i') }}</p>
                                    @if ($notification->entity_type === 'listing' && auth()->user()->hasRole('LANDLORD'))
                                        <a class="mt-3 inline-flex min-h-9 items-center gap-2 text-sm font-bold text-brand-700 underline decoration-brand-300 underline-offset-4 hover:text-brand-800" href="{{ route('landlord.listings.index') }}">
                                            {{ __('ui.notifications.view_listings') }}
                                            <i class="size-4" data-lucide="arrow-right"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                            @if (! $notification->read_at)
                                <form class="sm:shrink-0" method="POST" action="{{ route('notifications.read', $notification) }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button class="w-full sm:w-auto" type="submit" variant="secondary">{{ __('ui.notifications.mark_read') }}</x-ui.button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ol>

                <div class="mt-6">{{ $notifications->links() }}</div>
            @endif
        </div>
    </section>
@endsection
