<?php

namespace Benjaber\Permission\Middlewares;

use Closure;
use Benjaber\Permission\Exceptions\UnauthorizedException;

class PermissionMiddleware
{
    public function handle($request, Closure $next, $permission, $entityId)
    {
        if (app('auth')->guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $permissions = is_array($permission)
            ? $permission
            : explode('|', $permission);

        if (! auth()->user()->hasAnyPermission($permissions, $entityId)) {
            throw UnauthorizedException::forPermissions($permissions);
        }

        return $next($request);

    }
}
