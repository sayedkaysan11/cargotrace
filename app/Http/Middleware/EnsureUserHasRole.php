<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side role check. Example: ->middleware('role:admin,operator')
 * Must run after an authentication middleware (auth / auth:sanctum).
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->is_active === false || ! in_array($user->role?->value, $roles, true)) {
            abort(403, 'This action is unauthorized.');
        }

        return $next($request);
    }
}
