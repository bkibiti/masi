<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every "My Account" route: a staff-only login with no linked member has nothing to
 * scope these screens to, so it gets a 403 rather than leaking another member's data.
 */
class EnsureUserIsLinkedToMember
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->member !== null, 403);

        return $next($request);
    }
}
