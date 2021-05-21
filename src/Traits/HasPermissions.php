<?php

namespace Benjaber\Permission\Traits;

use Benjaber\Permission\Contracts\Permission;
use Benjaber\Permission\Exceptions\EntityNotExists;
use Benjaber\Permission\Exceptions\PermissionDoesNotExist;
use Benjaber\Permission\PermissionRegistrar;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasPermissions
{
    private $permissionClass;
    private $entityClass;

    public static function bootHasPermissions()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->permissions()->detach();
        });
    }

    public function getPermissionClass()
    {
        if (! isset($this->permissionClass)) {
            $this->permissionClass = app(PermissionRegistrar::class)->getPermissionClass();
        }

        return $this->permissionClass;
    }

    public function getEntityClass()
    {
        if (! isset($this->entityClass)) {
            $this->entityClass = app(PermissionRegistrar::class)->getEntityClass();
        }

        return $this->entityClass;
    }

    /**
     * A model may have multiple direct permissions.
     */
    public function permissions(): BelongsToMany
    {
        return $this->morphToMany(
            config('permission.models.permission'),
            'model',
            config('permission.table_names.model_has_permissions'),
            config('permission.column_names.model_morph_key'),
            'permission_id'
        )->withPivot([config('permission.entity.entity_key')]);
    }

    /*
     * Chack if the entity or not
     */
    private function chackEntityAvailability($entityId)
    {
        if(! $this->getEntityClass()->find($entityId)) {
            throw new EntityNotExists;
        }
    }

    /**
     * Determine if the model may perform the given permission.
     *
     * @param string|int|\Benjaber\Permission\Contracts\Permission $permission
     * @param $entityId
     * @return bool
     */
    public function hasPermissionTo($permission, $entityId): bool
    {
        $this->chackEntityAvailability($entityId);

        $permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permissionClass->findByName($permission);
        }

        if (is_int($permission)) {
            $permission = $permissionClass->findById($permission);
        }

        if (! $permission instanceof Permission) {
            throw new PermissionDoesNotExist;
        }

        return $this->hasDirectPermission($permission, $entityId);
    }

    /**
     * An alias to hasPermissionTo(), but avoids throwing an exception.
     *
     * @param string|int|\Benjaber\Permission\Contracts\Permission $permission
     *
     * @return bool
     */
    public function checkPermissionTo($permission, $entityId): bool
    {
        try {
            return $this->hasPermissionTo($permission, $entityId);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    /**
     * Determine if the model has any of the given permissions.
     *
     * @param array $permissions
     * @param $entityId
     *
     * @return bool
     * @throws \Exception
     */
    public function hasAnyPermission(array $permissions, $entityId): bool
    {
        $this->chackEntityAvailability($entityId);

        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if ($this->checkPermissionTo($permission, $entityId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the model has all of the given permissions.
     *
     * @param array $permissions
     *
     * @param $entityId
     * @return bool
     */
    public function hasAllPermissions(array $permissions, $entityId): bool
    {
        $this->chackEntityAvailability($entityId);

        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if (! $this->hasPermissionTo($permission, $entityId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the model has the given permission.
     *
     * @param string|int|\Benjaber\Permission\Contracts\Permission $permission
     *
     * @return bool
     * @throws PermissionDoesNotExist
     */
    public function hasDirectPermission($permission, $entityId): bool
    {
        $permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permissionClass->findByName($permission);
        }

        if (is_int($permission)) {
            $permission = $permissionClass->findById($permission);
        }

        if (! $permission instanceof Permission) {
            throw new PermissionDoesNotExist;
        }

        return $this->permissions()->wherePivot(config('permission.entity.entity_key'), $entityId)->wherePivot('permission_id', $permission->id)->exists();
    }

    /**
     * Return all the permissions the model has, both directly and via roles.
     */
    public function getAllPermissions(): Collection
    {
        /** @var Collection $permissions */
        $permissions = $this->permissions;


        return $permissions->sort()->values();
    }

    /**
     * Grant the given permission(s) to a role.
     *
     * @param string|array|\Benjaber\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return $this
     */
    public function givePermissionTo($permissions, $entityId)
    {
        $this->chackEntityAvailability($entityId);

        $permissionsArray = [];

        $permissions = collect($permissions)
            ->flatten()
            ->map(function ($permission) {
                if (empty($permission)) {
                    return false;
                }

                return $this->getStoredPermission($permission);
            })
            ->filter(function ($permission) {
                return $permission instanceof Permission;
            })
            ->each(function($permission) use ($entityId, &$permissionsArray) {
                $permissionsArray[$permission->id] = [config('permission.entity.entity_key') => $entityId];
            })
            ->flatten();

        $model = $this->getModel();

        if ($model->exists) {
            $this->permissions()->attach($permissionsArray);
            $model->load('permissions');
        } else {
            $class = \get_class($model);

            $class::saved(
                function ($object) use ($permissions, $model) {
                    static $modelLastFiredOn;
                    if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                        return;
                    }
                    $object->permissions()->attach($permissionsArray);
                    $object->load('permissions');
                    $modelLastFiredOn = $object;
                }
            );
        }

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Remove all current permissions and set the given ones.
     *
     * @param string|array|\Benjaber\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return $this
     */
    public function syncPermissions($permissions, $entityId)
    {
        $this->chackEntityAvailability($entityId);

        $this->permissions()->wherePivot(config('permission.entity.entity_key'), $entityId)->detach();

        return $this->givePermissionTo($permissions, $entityId);
    }

    /**
     * Revoke the given permission.
     *
     * @param \Benjaber\Permission\Contracts\Permission|\Benjaber\Permission\Contracts\Permission[]|string|string[] $permission
     *
     * @return $this
     */
    public function revokePermissionTo($permission, $entityId)
    {
        $this->chackEntityAvailability($entityId);

        $this->permissions()->wherePivot(config('permission.entity.entity_key'), $entityId)->detach($this->getStoredPermission($permission));

        $this->forgetCachedPermissions();

        $this->load('permissions');

        return $this;
    }

    public function getPermissionNames(): Collection
    {
        return $this->permissions->pluck('name');
    }

    /**
     * @param string|array|\Benjaber\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return \Benjaber\Permission\Contracts\Permission|\Benjaber\Permission\Contracts\Permission[]|\Illuminate\Support\Collection
     */
    protected function getStoredPermission($permissions)
    {
        $permissionClass = $this->getPermissionClass();

        if (is_numeric($permissions)) {
            return $permissionClass->findById($permissions);
        }

        if (is_string($permissions)) {
            return $permissionClass->findByName($permissions);
        }

        if (is_array($permissions)) {
            return $permissionClass
                ->whereIn('name', $permissions)
                ->get();
        }

        return $permissions;
    }

    /**
     * Forget the cached permissions.
     */
    public function forgetCachedPermissions()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

}
