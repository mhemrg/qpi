<?php
namespace Navac\Qpi\Syntax\Util;

class Field
{
    /**
     * The name of field
     *
     * @var string
     */
    public $name;

    /**
     * Is this field must be in group by list?
     *
     * @var bool
     */
    public $isInGroupBy;

    public $isInOrderBy;

    public function __toString()
    {
        return $this->name;
    }
}
