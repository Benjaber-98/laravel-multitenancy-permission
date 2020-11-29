<?php

namespace Benjaber\Permission\Models;

use Benjaber\Permission\Contracts\Permission as PermissionContract;
use Benjaber\Permission\Exceptions\PermissionAlreadyExists;
use Benjaber\Permission\Exceptions\PermissionDoesNotExist;
use Benjaber\Permission\PermissionRegistrar;
use Benjaber\Permission\Traits\RefreshesPermissionCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model implements PermissionContract
{
    use RefreshesPermissionCache;

    protected $guarded = ['id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function getTable()
    {
        return config('permission.table_names.permissions', parent::getTable());
    }

    public static function create(array $attributes = [])
    {
        $permission = static::getPermissions(['name' => $attributes['name']])->first();

        if ($permission) {
            throw PermissionAlreadyExists::create($attributes['name']);
        }

        return static::query()->create($attributes);
    }

//
//    /**
//     * A permission belongs to some users of the model associated with its guard.
//     */
//    public function users(): BelongsToMany
//    {
//        return $this->morphedByMany(
//            getModelForGuard($this->attributes['guard_name']),
//            'model',
//            config('permission.table_names.model_has_permissions'),
//            'permission_id',
//            config('permission.column_names.model_morph_key')
//        );
//    }

    /**
     * Find a permission by its name (and optionally guardName).
     *
     * @param string $name
     *
     * @throws \Benjaber\Permission\Exceptions\PermissionDoesNotExist
     *
     * @return \Benjaber\Permission\Contracts\Permission
     */
    public static function findByName(string $name): PermissionContract
    {
        $permission = static::getPermissions(['name' => $name])->first();
        if (! $permission) {
            throw PermissionDoesNotExist::create($name);
        }

        return $permission;
    }

    /**
     * Find a permission by its id (and optionally guardName).
     *
     * @param int $id
     * @param string|null $guardName
     *
     * @throws \Benjaber\Permission\Exceptions\PermissionDoesNotExist
     *
     * @return \Benjaber\Permission\Contracts\Permission
     */
    public static function findById(int $id): PermissionContract
    {
        $permission = static::getPermissions(['id' => $id])->first();

        if (! $permission) {
            throw PermissionDoesNotExist::withId($id);
        }

        return $permission;
    }

    /**
     * Find or create permission by its name (and optionally guardName).
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @return \Benjaber\Permission\Contracts\Permission
     */
    public static function findOrCreate(string $name): PermissionContract
    {
        $permission = static::getPermissions(['name' => $name])->first();

        if (! $permission) {
            return static::query()->create(['name' => $name]);
        }

        return $permission;
    }

    /**
     * Get the current cached permissions.
     */
    protected static function getPermissions(array $params = []): Collection
    {
        return app(PermissionRegistrar::class)
            ->setPermissionClass(static::class)
            ->getPermissions($params);
    }
}
