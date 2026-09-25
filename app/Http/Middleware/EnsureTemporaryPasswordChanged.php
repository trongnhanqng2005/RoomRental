<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTemporaryPasswordChanged
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user !== null
            && $user->must_change_password
            && ! $request->routeIs('password.change.required', 'password.change.update', 'logout')
        ) {
            return redirect()->route('password.change.required');
        }

        return $next($request);
    }
}
