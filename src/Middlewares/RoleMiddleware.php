<?php

namespace Benjaber\Permission\Middlewares;

use Closure;
use Benjaber\Permission\Exceptions\UnauthorizedException;

class RoleMiddleware
{
    public function handle($request, Closure $next, $role, $entityId)
    {
        if (auth()->guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $roles = is_array($role)
            ? $role
            : explode('|', $role);

        if (! auth()->user()->hasAnyRole($roles, $entityId)) {
            throw UnauthorizedException::forRoles($roles);
        }

        return $next($request);
    }
}
