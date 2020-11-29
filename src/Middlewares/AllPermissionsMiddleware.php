<?php

namespace Benjaber\Permission\Middlewares;

use Benjaber\Permission\Exceptions\UnauthorizedException;
use Closure;

class AllPermissionsMiddleware
{
    public function handle($request, Closure $next, $permission, $entityId)
    {
        if (auth()->guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $permissions = is_array($permission)
            ? $permission
            : explode('|', $permission);

        if (! auth()->user()->hasAllPermissions($permissions, $entityId)) {
            throw UnauthorizedException::forPermissions($permissions);
        }

        return $next($request);

    }
}
