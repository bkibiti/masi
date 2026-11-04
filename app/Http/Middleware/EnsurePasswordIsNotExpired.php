<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A temporary password (set when a login is created or reset) forces a change before the
 * user can reach anything else. The force-change screen and logout stay reachable so the
 * user isn't stuck.
 */
class EnsurePasswordIsNotExpired
{
    /** @var array<int, string> */
    private const ALLOWED_ROUTES = ['account.force-password', 'account.force-password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            return redirect()->route('account.force-password');
        }

        return $next($request);
    }
}
