<?php

namespace Benjaber\Permission\Middlewares;

use Closure;
use Benjaber\Permission\Exceptions\UnauthorizedException;

class AllRolesMiddleware
{
    public function handle($request, Closure $next, $role, $entityId)
    {
        if (auth()->guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $roles = is_array($role)
            ? $role
            : explode('|', $role);

        if (! auth()->user()->hasAllRoles($roles, $entityId)) {
            throw UnauthorizedException::forRoles($roles);
        }

        return $next($request);
    }
}
