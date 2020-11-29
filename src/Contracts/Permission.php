<?php

namespace Benjaber\Permission\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Permission
{

    /**
     * Find a permission by its name.
     *
     * @param string $name
     *
     * @throws \Benjaber\Permission\Exceptions\PermissionDoesNotExist
     *
     * @return Permission
     */
    public static function findByName(string $name): self;

    /**
     * Find a permission by its id.
     *
     * @param int $id
     *
     * @throws \Benjaber\Permission\Exceptions\PermissionDoesNotExist
     *
     * @return Permission
     */
    public static function findById(int $id): self;

    /**
     * Find or Create a permission by its name.
     *
     * @param string $name
     *
     * @return Permission
     */
    public static function findOrCreate(string $name): self;
}
