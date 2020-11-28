<?php


namespace Benjaber\Permission\Exceptions;

use InvalidArgumentException;

class EntityNotExists extends InvalidArgumentException
{
    public static function create($entityId)
    {
        return new static("Entity with id ${$entityId} is not exists.");
    }
}
