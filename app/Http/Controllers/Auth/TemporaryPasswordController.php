<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeTemporaryPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TemporaryPasswordController extends Controller
{
    public function create(): View
    {
        abort_unless(request()->user()->must_change_password, 404);

        return view('auth.change-temporary-password');
    }

    public function update(ChangeTemporaryPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'password_hash' => Hash::make($request->validated('password')),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect('/')->with('status', __('ui.password_change.updated'));
    }
}
