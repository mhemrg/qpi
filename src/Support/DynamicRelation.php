<?php

namespace Navac\Qpi\Support;

class DynamicRelation
{
    /**
     * List of dynamically added relations
     *
     * @var array
     */
    protected static $relations;

    /**
     * Add a relation
     *
     * @param string $relation The name of the relation
     * @param string $model    The name of the class whitch relation must add to it
     * @param callable $fn     A callable that must invoke when calling relation
     */
    public static function add($relation, $model, $fn)
    {
        static::$relations[$model][$relation] = $fn;
    }

    /**
     * Get a relation
     *
     * @param  string $relation The name of the relation
     * @param  string $model    The name of the class whitch relation added to it
     * @return callable
     */
    public static function get($relation, $model)
    {
        return static::$relations[$model][$relation];
    }

    /**
     * Check a relation exists or not
     *
     * @param  string $relation The name of the relation
     * @param  string $model    The name of the class whitch relation added to it
     * @return bool
     */
    public static function relationExists($relation, $model)
    {
        return array_key_exists($relation, static::$relations[$model]);
    }
}
