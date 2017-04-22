<?php
namespace Navac\Qpi\Syntax;

use Navac\Qpi\Syntax\Util\ {
    Model as UtilModel
};

class Evaluator
{
    /**
     * Fetch each model
     *
     * @param  array $parseTree
     * @return array
     */
    public static function eval(array $parseTree) : array
    {
        static $output = [];

        foreach ($parseTree as $model) {
            array_push($output, $model->fetch());
        }

        return $output;
    }
}
