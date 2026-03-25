<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorChallengeSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('two_factor.id')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
