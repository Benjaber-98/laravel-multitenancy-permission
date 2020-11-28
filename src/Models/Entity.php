<?php


namespace Benjaber\Permission\Models;


use Benjaber\Permission\Contracts\Entity as EntityContract;
use Illuminate\Database\Eloquent\Model;

class Entity extends Model implements EntityContract
{

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function getTable()
    {
        return config('permission.table_names.entities', parent::getTable());
    }
}
