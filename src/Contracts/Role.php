<?php

namespace Benjaber\Permission\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Role
{
    /**
     * A role may be given various permissions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissions(): BelongsToMany;

    /**
     * Find a role by its name and guard name.
     *
     * @param string $name
     *
     * @return \Benjaber\Permission\Contracts\Role
     *
     * @throws \Benjaber\Permission\Exceptions\RoleDoesNotExist
     */
    public static function findByName(string $name): self;

    /**
     * Find a role by its id and guard name.
     *
     * @param int $id
     *
     * @return \Benjaber\Permission\Contracts\Role
     *
     * @throws \Benjaber\Permission\Exceptions\RoleDoesNotExist
     */
    public static function findById(int $id): self;

    /**
     * Find or create a role by its name and guard name.
     *
     * @param string $name
     *
     * @return \Benjaber\Permission\Contracts\Role
     */
    public static function findOrCreate(string $name): self;

    /**
     * Determine if the user may perform the given permission.
     *
     * @param string|\Benjaber\Permission\Contracts\Permission $permission
     *
     * @return bool
     */
    public function hasPermissionTo($permission): bool;
}
