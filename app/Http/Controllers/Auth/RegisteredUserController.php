<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use RuntimeException;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = new User;
            $user->email = $validated['email'] ?? null;
            $user->phone = $validated['phone'] ?? null;
            $user->password_hash = Hash::make($validated['password']);
            $user->account_status = 'ACTIVE';
            $user->failed_login_count = 0;
            $user->login_blocked_until = null;
            $user->must_change_password = false;
            $user->last_login_at = null;
            $user->save();

            $user->profile()->create([
                'full_name' => $validated['full_name'],
            ]);

            $renterRole = Role::where('code', 'RENTER')->first();

            if (! $renterRole) {
                throw new RuntimeException('The required RENTER role is not configured.');
            }

            $user->roles()->attach($renterRole->id, [
                'assigned_by' => null,
                'assigned_at' => now(),
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/');
    }
}
