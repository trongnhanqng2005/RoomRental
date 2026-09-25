@extends('layouts.app')

@section('title', __('ui.user_management.title'))

@section('content')
    <section class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <header class="mb-7">
            <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.user_management.eyebrow') }}</p>
            <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950">{{ __('ui.user_management.heading') }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">{{ __('ui.user_management.description') }}</p>
        </header>

        <form class="mb-6 grid gap-4 rounded-panel border border-line bg-white p-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end" method="GET" action="{{ route('admin.users.index') }}">
            <div class="sm:col-span-2">
                <label class="mb-2 block text-sm font-bold text-slate-800" for="q">{{ __('ui.user_management.search') }}</label>
                <input class="field-control" id="q" name="q" type="search" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('ui.user_management.search_placeholder') }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-800" for="role">{{ __('ui.user_management.role_filter') }}</label>
                <select class="field-control" id="role" name="role">
                    <option value="">{{ __('ui.user_management.all_roles') }}</option>
                    @foreach (['RENTER', 'LANDLORD', 'ADMIN', 'SUPER_ADMIN'] as $role)
                        <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ __('ui.user_management.roles_label.'.$role) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-bold text-slate-800" for="status">{{ __('ui.user_management.status_filter') }}</label>
                <select class="field-control" id="status" name="status">
                    <option value="">{{ __('ui.user_management.all_statuses') }}</option>
                    <option value="ACTIVE" @selected(($filters['status'] ?? '') === 'ACTIVE')>{{ __('ui.user_management.status_active') }}</option>
                    <option value="LOCKED" @selected(($filters['status'] ?? '') === 'LOCKED')>{{ __('ui.user_management.status_locked') }}</option>
                </select>
            </div>
            <div class="flex flex-col gap-2 sm:col-span-2 lg:col-span-4 sm:flex-row sm:justify-end">
                <a class="inline-flex min-h-11 items-center justify-center rounded-control border border-line px-4 text-sm font-bold text-slate-700 hover:bg-slate-50" href="{{ route('admin.users.index') }}">{{ __('ui.user_management.clear_filters') }}</a>
                <x-ui.button type="submit" variant="primary">{{ __('ui.user_management.apply_filters') }}</x-ui.button>
            </div>
        </form>

        @if ($users->isEmpty())
            <div class="rounded-panel border border-line bg-white px-6 py-12 text-center">
                <h2 class="text-xl font-bold text-slate-950">{{ __('ui.user_management.empty_heading') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('ui.user_management.empty_description') }}</p>
            </div>
        @else
            <div class="hidden overflow-hidden rounded-panel border border-line bg-white md:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <caption class="sr-only">{{ __('ui.user_management.heading') }}</caption>
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-5 py-4" scope="col">{{ __('ui.user_management.user') }}</th>
                                <th class="px-5 py-4" scope="col">{{ __('ui.user_management.contact') }}</th>
                                <th class="px-5 py-4" scope="col">{{ __('ui.user_management.roles') }}</th>
                                <th class="px-5 py-4" scope="col">{{ __('ui.user_management.status') }}</th>
                                <th class="px-5 py-4" scope="col">{{ __('ui.user_management.created_at') }}</th>
                                <th class="px-5 py-4" scope="col"><span class="sr-only">{{ __('ui.user_management.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($users as $managedUser)
                                <tr class="text-slate-700">
                                    <td class="px-5 py-4 font-bold text-slate-950">{{ $managedUser->profile?->full_name ?? '#'.$managedUser->id }}</td>
                                    <td class="px-5 py-4"><span class="block">{{ $managedUser->email ?? '—' }}</span><span class="mt-1 block text-xs text-slate-500">{{ $managedUser->phone ?? '—' }}</span></td>
                                    <td class="px-5 py-4"><div class="flex flex-wrap gap-1.5">@foreach ($managedUser->roles as $role)<span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ __('ui.user_management.roles_label.'.$role->code) }}</span>@endforeach</div></td>
                                    <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $managedUser->account_status === 'ACTIVE' ? 'bg-success-50 text-success-700' : 'bg-danger-50 text-danger-700' }}">{{ __('ui.user_management.status_'.strtolower($managedUser->account_status)) }}</span></td>
                                    <td class="whitespace-nowrap px-5 py-4">{{ $managedUser->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-4 text-right"><a class="inline-flex min-h-10 items-center rounded-control px-3 font-bold text-brand-700 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-brand-600" href="{{ route('admin.users.show', $managedUser) }}">{{ __('ui.user_management.view') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-3 md:hidden">
                @foreach ($users as $managedUser)
                    <article class="rounded-panel border border-line bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="font-bold text-slate-950">{{ $managedUser->profile?->full_name ?? '#'.$managedUser->id }}</h2>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold {{ $managedUser->account_status === 'ACTIVE' ? 'bg-success-50 text-success-700' : 'bg-danger-50 text-danger-700' }}">{{ __('ui.user_management.status_'.strtolower($managedUser->account_status)) }}</span>
                        </div>
                        <p class="mt-2 break-all text-sm text-slate-700">{{ $managedUser->email ?? $managedUser->phone ?? '—' }}</p>
                        <div class="mt-3 flex flex-wrap gap-1.5">@foreach ($managedUser->roles as $role)<span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ __('ui.user_management.roles_label.'.$role->code) }}</span>@endforeach</div>
                        <a class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600" href="{{ route('admin.users.show', $managedUser) }}">{{ __('ui.user_management.view') }}</a>
                    </article>
                @endforeach
            </div>

            <nav class="mt-6" aria-label="{{ __('ui.user_management.pagination') }}">{{ $users->links() }}</nav>
        @endif
    </section>
@endsection
