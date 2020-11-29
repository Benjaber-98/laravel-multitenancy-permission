<?php

namespace Benjaber\Permission\Models;

use Benjaber\Permission\Contracts\Role as RoleContract;
use Benjaber\Permission\Exceptions\RoleAlreadyExists;
use Benjaber\Permission\Exceptions\RoleDoesNotExist;
use Benjaber\Permission\Traits\HasPermissions;
use Benjaber\Permission\Traits\RefreshesPermissionCache;
use Illuminate\Database\Eloquent\Model;

class Role extends Model implements RoleContract
{
    use HasPermissions;
    use RefreshesPermissionCache;

    protected $guarded = ['id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function getTable()
    {
        return config('permission.table_names.roles', parent::getTable());
    }

    public static function create(array $attributes = [])
    {
        if (static::where('name', $attributes['name'])->first()) {
            throw RoleAlreadyExists::create($attributes['name']);
        }

        return static::query()->create($attributes);
    }

    /**
     * A role may be given various permissions.
     */
//    public function permissions(): BelongsToMany
//    {
//        return $this->belongsToMany(
//            config('permission.models.permission'),
//            config('permission.table_names.role_has_permissions'),
//            'role_id',
//            'permission_id'
//        );
//    }

//    /**
//     * A role belongs to some users of the model associated with its guard.
//     */
//    public function users(): BelongsToMany
//    {
//        return $this->morphedByMany(
//            getModelForGuard($this->attributes['guard_name']),
//            'model',
//            config('permission.table_names.model_has_roles'),
//            'role_id',
//            config('permission.column_names.model_morph_key')
//        );
//    }

    /**
     * Find a role by its name and guard name.
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @return \Benjaber\Permission\Contracts\Role|\Benjaber\Permission\Models\Role
     *
     * @throws \Benjaber\Permission\Exceptions\RoleDoesNotExist
     */
    public static function findByName(string $name): RoleContract
    {

        $role = static::where('name', $name)->first();

        if (! $role) {
            throw RoleDoesNotExist::named($name);
        }

        return $role;
    }

    public static function findById(int $id): RoleContract
    {

        $role = static::where('id', $id)->first();

        if (! $role) {
            throw RoleDoesNotExist::withId($id);
        }

        return $role;
    }

    /**
     * Find or create role by its name (and optionally guardName).
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @return \Benjaber\Permission\Contracts\Role
     */
    public static function findOrCreate(string $name): RoleContract
    {
        $role = static::where('name', $name)->first();

        if (! $role) {
            return static::query()->create(['name' => $name]);
        }

        return $role;
    }

    /**
     * Determine if the user may perform the given permission.
     *
     * @param string|Permission $permission
     *
     * @return bool
     *
     */
    public function hasPermissionTo($permission): bool
    {
        if (config('permission.enable_wildcard_permission', false)) {
            return $this->hasWildcardPermission($permission);
        }

        $permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permissionClass->findByName($permission);
        }

        if (is_int($permission)) {
            $permission = $permissionClass->findById($permission);
        }

        return $this->permissions->contains('id', $permission->id);
    }
}
