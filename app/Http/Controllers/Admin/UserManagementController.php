<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Models\User;
use App\Services\UserAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(UserIndexRequest $request, UserAdministrationService $service): View
    {
        $filters = $request->validated();
        $users = $service->paginate($filters);

        return view('admin.users.index', compact('users', 'filters'));
    }

    public function show(User $user, UserAdministrationService $service): Response
    {
        abort_unless(request()->user()->hasAnyRole('ADMIN', 'SUPER_ADMIN'), 403);

        $temporaryPassword = request()->session()->pull('temporary_password');
        $response = response()->view('admin.users.show', [
            'managedUser' => $service->detail($user),
            'temporaryPassword' => $temporaryPassword,
        ]);

        if ($temporaryPassword !== null) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }

    public function lock(User $user, UserAdministrationService $service): RedirectResponse
    {
        $service->lock($user, request()->user());

        return redirect()->route('admin.users.show', $user)->with('status', __('ui.user_management.locked'));
    }

    public function unlock(User $user, UserAdministrationService $service): RedirectResponse
    {
        $service->unlock($user, request()->user());

        return redirect()->route('admin.users.show', $user)->with('status', __('ui.user_management.unlocked'));
    }

    public function resetPassword(User $user, UserAdministrationService $service): RedirectResponse
    {
        $temporaryPassword = $service->resetPassword($user, request()->user());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('temporary_password', $temporaryPassword);
    }

    public function promote(User $user, UserAdministrationService $service): RedirectResponse
    {
        $service->promote($user, request()->user());

        return redirect()->route('admin.users.show', $user)->with('status', __('ui.user_management.promoted'));
    }

    public function revoke(User $user, UserAdministrationService $service): RedirectResponse
    {
        $service->revokeAdmin($user, request()->user());

        return redirect()->route('admin.users.show', $user)->with('status', __('ui.user_management.revoked'));
    }
}
