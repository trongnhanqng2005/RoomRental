<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user()->load(['profile', 'roles']);

        if (! $user->profile) {
            throw new RuntimeException('The user profile is missing.');
        }

        $roleLabels = [
            'RENTER' => __('ui.profile.roles.renter'),
            'LANDLORD' => __('ui.profile.roles.landlord'),
            'ADMIN' => __('ui.profile.roles.admin'),
            'SUPER_ADMIN' => __('ui.profile.roles.super_admin'),
        ];

        $displayRoles = $user->roles
            ->map(fn ($role) => $roleLabels[$role->code] ?? $role->name)
            ->values();

        $avatarUrl = $user->profile->avatar_url;
        $avatarSrc = $avatarUrl === null
            ? null
            : (filter_var($avatarUrl, FILTER_VALIDATE_URL) ? $avatarUrl : asset(ltrim($avatarUrl, '/')));

        $initials = collect(preg_split('/\s+/u', trim($user->profile->full_name), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return view('profile.show', [
            'user' => $user,
            'profile' => $user->profile,
            'displayRoles' => $displayRoles,
            'avatarSrc' => $avatarSrc,
            'initials' => $initials,
        ]);
    }

    public function update(UpdateProfileRequest $request, ProfileService $profileService): RedirectResponse
    {
        $profileService->update($request->user(), $request->validated());

        return back()->with('status', __('ui.profile.updated'));
    }
}
