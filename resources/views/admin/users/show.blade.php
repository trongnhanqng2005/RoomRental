@extends('layouts.app')

@section('title', __('ui.user_management.detail_title'))

@section('content')
    @php
        $actor = auth()->user();
        $targetIsSuperAdmin = $managedUser->hasRole('SUPER_ADMIN');
        $targetIsAdmin = $managedUser->hasRole('ADMIN');
        $isSelf = (int) $actor->id === (int) $managedUser->id;
        $isSuperAdmin = $actor->hasRole('SUPER_ADMIN');
        $canManage = ! $isSelf && ! $targetIsSuperAdmin && (! $targetIsAdmin || $isSuperAdmin);
    @endphp

    <section class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <a class="inline-flex min-h-10 items-center rounded-control px-2 text-sm font-bold text-brand-700 hover:bg-brand-50" href="{{ route('admin.users.index') }}">← {{ __('ui.user_management.back_to_list') }}</a>
        <header class="mb-6 mt-5">
            <p class="mb-2 text-sm font-bold text-brand-700">{{ __('ui.user_management.eyebrow') }} · #{{ $managedUser->id }}</p>
            <h1 class="text-3xl font-bold tracking-[-0.04em] text-slate-950">{{ __('ui.user_management.detail_heading') }}</h1>
        </header>

        @if (session('status'))
            <p class="mb-5 rounded-control border border-success-100 bg-success-50 p-4 text-sm font-semibold text-success-700" role="status">{{ session('status') }}</p>
        @endif

        @if ($temporaryPassword !== null)
            <section class="mb-5 rounded-panel border border-warning-200 bg-warning-50 p-5" aria-labelledby="temporary-password-heading">
                <h2 class="font-bold text-warning-900" id="temporary-password-heading">{{ __('ui.user_management.temporary_password_heading') }}</h2>
                <p class="mt-2 text-sm leading-6 text-warning-800">{{ __('ui.user_management.temporary_password_description') }}</p>
                <code class="mt-4 block select-all break-all rounded-control border border-warning-200 bg-white p-3 font-mono text-base font-bold text-slate-950">{{ $temporaryPassword }}</code>
            </section>
        @endif

        <section class="rounded-panel border border-line bg-white p-5 sm:p-7" aria-labelledby="user-information-heading">
            <h2 class="text-xl font-bold text-slate-950" id="user-information-heading">{{ $managedUser->profile?->full_name ?? '#'.$managedUser->id }}</h2>
            <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.user_management.full_name') }}</dt><dd class="mt-1 font-semibold text-slate-800">{{ $managedUser->profile?->full_name ?? '—' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.user_management.email') }}</dt><dd class="mt-1 break-all text-slate-700">{{ $managedUser->email ?? '—' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.user_management.phone') }}</dt><dd class="mt-1 text-slate-700">{{ $managedUser->phone ?? '—' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.user_management.account_status') }}</dt><dd class="mt-1"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $managedUser->account_status === 'ACTIVE' ? 'bg-success-50 text-success-700' : 'bg-danger-50 text-danger-700' }}">{{ __('ui.user_management.status_'.strtolower($managedUser->account_status)) }}</span></dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.user_management.roles') }}</dt><dd class="mt-1 flex flex-wrap gap-1.5">@forelse ($managedUser->roles as $role)<span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ __('ui.user_management.roles_label.'.$role->code) }}</span>@empty<span>—</span>@endforelse</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('ui.user_management.created_at') }}</dt><dd class="mt-1 text-slate-700">{{ $managedUser->created_at->format('d/m/Y H:i') }}</dd></div>
            </dl>
        </section>

        @if ($targetIsSuperAdmin)
            <p class="mt-5 rounded-control border border-information-100 bg-information-50 p-4 text-sm font-semibold text-information-700">{{ __('ui.user_management.protected_account') }}</p>
        @elseif ($targetIsAdmin && ! $isSuperAdmin)
            <p class="mt-5 rounded-control border border-information-100 bg-information-50 p-4 text-sm font-semibold text-information-700">{{ __('ui.user_management.read_only_admin') }}</p>
        @elseif ($isSelf)
            <p class="mt-5 rounded-control border border-information-100 bg-information-50 p-4 text-sm font-semibold text-information-700">{{ __('ui.user_management.self_protected') }}</p>
        @endif

        @if ($canManage)
            <section class="mt-5 rounded-panel border border-line bg-white p-5 sm:p-7" aria-labelledby="management-actions-heading">
                <h2 class="text-lg font-bold text-slate-950" id="management-actions-heading">{{ __('ui.user_management.actions') }}</h2>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    @if ($managedUser->account_status === 'ACTIVE')
                        <form method="POST" action="{{ route('admin.users.lock', $managedUser) }}" onsubmit="return confirm(@js(__('ui.user_management.lock_confirmation')))">
                            @csrf @method('PATCH')
                            <button class="inline-flex min-h-11 w-full items-center justify-center rounded-control bg-danger-600 px-4 text-sm font-bold text-white hover:bg-danger-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-600 sm:w-auto" type="submit">{{ __('ui.user_management.lock') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.users.unlock', $managedUser) }}" onsubmit="return confirm(@js(__('ui.user_management.unlock_confirmation')))">
                            @csrf @method('PATCH')
                            <button class="inline-flex min-h-11 w-full items-center justify-center rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 sm:w-auto" type="submit">{{ __('ui.user_management.unlock') }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.users.password-reset', $managedUser) }}" onsubmit="return confirm(@js(__('ui.user_management.reset_confirmation')))">
                        @csrf
                        <button class="inline-flex min-h-11 w-full items-center justify-center rounded-control border border-line px-4 text-sm font-bold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 sm:w-auto" type="submit">{{ __('ui.user_management.reset_password') }}</button>
                    </form>
                    @if ($isSuperAdmin && ! $targetIsAdmin)
                        <form method="POST" action="{{ route('admin.users.promote', $managedUser) }}" onsubmit="return confirm(@js(__('ui.user_management.promote_confirmation')))">
                            @csrf
                            <button class="inline-flex min-h-11 w-full items-center justify-center rounded-control border border-brand-200 bg-brand-50 px-4 text-sm font-bold text-brand-800 hover:bg-brand-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 sm:w-auto" type="submit">{{ __('ui.user_management.promote') }}</button>
                        </form>
                    @elseif ($isSuperAdmin && $targetIsAdmin)
                        <form method="POST" action="{{ route('admin.users.revoke', $managedUser) }}" onsubmit="return confirm(@js(__('ui.user_management.revoke_confirmation')))">
                            @csrf @method('DELETE')
                            <button class="inline-flex min-h-11 w-full items-center justify-center rounded-control border border-danger-200 bg-danger-50 px-4 text-sm font-bold text-danger-700 hover:bg-danger-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-600 sm:w-auto" type="submit">{{ __('ui.user_management.revoke') }}</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif
    </section>
@endsection
