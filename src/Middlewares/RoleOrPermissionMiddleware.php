<?php

namespace Benjaber\Permission\Middlewares;

use Closure;
use Illuminate\Support\Facades\Auth;
use Benjaber\Permission\Exceptions\UnauthorizedException;

class RoleOrPermissionMiddleware
{
    public function handle($request, Closure $next, $roleOrPermission, $entityId)
    {
        if (auth()->guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $rolesOrPermissions = is_array($roleOrPermission)
            ? $roleOrPermission
            : explode('|', $roleOrPermission);

        if (! auth()->user()->hasAnyRole($rolesOrPermissions, $entityId) && ! auth()->user()->hasAnyPermission($rolesOrPermissions, $entityId)) {
            throw UnauthorizedException::forRolesOrPermissions($rolesOrPermissions);
        }

        return $next($request);
    }
}
