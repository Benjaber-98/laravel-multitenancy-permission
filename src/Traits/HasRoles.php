<?php

namespace Benjaber\Permission\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Benjaber\Permission\Contracts\Role;
use Benjaber\Permission\PermissionRegistrar;

trait HasRoles
{
    use HasPermissions;

    private $roleClass;

    public static function bootHasRoles()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->roles()->detach();
        });
    }

    public function getRoleClass()
    {
        if (! isset($this->roleClass)) {
            $this->roleClass = app(PermissionRegistrar::class)->getRoleClass();
        }

        return $this->roleClass;
    }

    /**
     * A model may have multiple roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            'role_id'
        )->withPivot([config('permission.entity.entity_key')]);
    }

    /**
     * Assign the given role to the model.
     *
     * @param array|string|\Benjaber\Permission\Contracts\Role ...$roles
     *
     * @return $this
     */
    public function assignRole($roles, $entityId)
    {
        $this->chackEntityAvailability($entityId);

        $roles = collect($roles)
            ->flatten()
            ->map(function ($role) {
                if (empty($role)) {
                    return false;
                }

                return $this->getStoredRole($role);
            })
            ->filter(function ($role) {
                return $role instanceof Role;
            })
            ->map(function($role) use ($entityId) {
                return [
                    'role_id' => $role->id,
                    config('permission.entity.entity_key') => $entityId
                ];
            })
            ->all();

        $model = $this->getModel();

        if ($model->exists) {
            $this->roles()->sync($roles, false);
            $model->load('roles');
        } else {
            $class = \get_class($model);

            $class::saved(
                function ($object) use ($roles, $model) {
                    static $modelLastFiredOn;
                    if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                        return;
                    }
                    $object->roles()->sync($roles, false);
                    $object->load('roles');
                    $modelLastFiredOn = $object;
                }
            );
        }

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Revoke the given role from the model.
     *
     * @param string|\Benjaber\Permission\Contracts\Role $role
     */
    public function removeRole($role)
    {
        $this->chackEntityAvailability($entityId);

        $this->roles()->wherePivot(config('permission.entity.entity_key'), $entityId)->detach($this->getStoredRole($role));

        $this->load('roles');

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Remove all current roles and set the given ones.
     *
     * @param  array|\Benjaber\Permission\Contracts\Role|string  ...$roles
     *
     * @return $this
     */
    public function syncRoles($roles, $entityId)
    {
        $this->chackEntityAvailability($entityId);

        $this->roles()->wherePivot(config('permission.entity.entity_key'), $entityId)->detach();

        return $this->assignRole($roles, $entityId);
    }

    /**
     * Determine if the model has (one of) the given role(s).
     *
     * @param string|int|array|\Benjaber\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     * @return bool
     */
    public function hasRole($roles, $entityId): bool
    {
        $this->chackEntityAvailability($entityId);

        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $this->roles->where('pivot.'.config('permission.entity.entity_key'), $entityId)->contains('name', $roles);
        }

        if (is_int($roles)) {
            return $this->roles->where('pivot.'.config('permission.entity.entity_key'), $entityId)->contains('id', $roles);
        }

        if ($roles instanceof Role) {
            return $this->roles->where('pivot.'.config('permission.entity.entity_key'), $entityId)->contains('id', $roles->id);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role, $entityId)) {
                    return true;
                }
            }

            return false;
        }

        return $roles->intersect($this->roles->where('pivot.'.config('permission.entity.entity_key'), $entityId))->isNotEmpty();
    }

    /**
     * Determine if the model has any of the given role(s).
     *
     * Alias to hasRole() but without Guard controls
     *
     * @param string|int|array|\Benjaber\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasAnyRole($roles, $entityId): bool
    {
        return $this->hasRole($roles, $entityId);
    }

    /**
     * Determine if the model has all of the given role(s).
     *
     * @param  string|array|\Benjaber\Permission\Contracts\Role|\Illuminate\Support\Collection  $roles
     * @return bool
     */
    public function hasAllRoles($roles, $entityId): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $this->roles->where('pivot.'.config('permission.entity.entity_key'), $entityId)->contains('name', $roles);
        }

        if ($roles instanceof Role) {
            return $this->roles->where('pivot.'.config('permission.entity.entity_key'), $entityId)->contains('id', $roles->id);
        }

        $roles = collect()->make($roles)->map(function ($role) {
            return $role instanceof Role ? $role->name : $role;
        });

        return $roles->intersect($this->getRoleNames()) == $roles;
    }


    public function getRoleNames(): Collection
    {
        return $this->roles->pluck('name');
    }

    protected function getStoredRole($role): Role
    {
        $roleClass = $this->getRoleClass();

        if (is_numeric($role)) {
            return $roleClass->findById($role);
        }

        if (is_string($role)) {
            return $roleClass->findByName($role);
        }

        return $role;
    }

    protected function convertPipeToArray(string $pipeString)
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = substr($pipeString, 0, 1);
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (! in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }
}
