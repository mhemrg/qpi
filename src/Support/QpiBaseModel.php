<?php

namespace Navac\Qpi\Support;

use Navac\Qpi\Support\DynamicRelation;
use Illuminate\Database\Eloquent\Model;

class QpiBaseModel extends Model
{
    public function __call($method, $parameters) {
        $class = get_class($this);

        // if method exists in qpi's dynamic relations list, invoke it.
        if (DynamicRelation::relationExists($method, $class)) {
            $function = DynamicRelation::get($method, $class);
            return $function( $this );
        }

        #i: No relation found, return the call to parent (Eloquent) to handle it.
        return parent::__call($method, $parameters);
    }
}
