<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
// use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {

        $user = $request->user();

        // 1. Ensure the user exists and has a properly cast Role enum
        if (! $user || ! $user->role instanceof Role) {
            abort(403, 'Unauthorized.');
        }

        // 2. If the route specifies required roles, ensure the user has one of them
        if (! empty($roles) && ! in_array($user->role->value, $roles, true)) {
            abort(403, 'This action requires a different role.');
        }

        return $next($request);
    }
}
