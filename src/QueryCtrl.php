<?php
namespace Navac\Qpi;

use Navac\Qpi\Syntax\ {
    Lexer,
    QueryParser,
    Evaluator
};
use App\Http\Controllers\Controller;

class QueryCtrl extends Controller
{
    public static $userModels = [];

    public function index($query)
    {
        $config = include config_path('qpi.php');
        self::$userModels = $config['models'];

        try {
            $tokens = Lexer::run($query);
            $parseTree = (new QueryParser($tokens))->parse();
            $result = Evaluator::eval($parseTree);

            return [
                'ok' => true,
                'data' => $result
            ];

        } catch (\Exception $e) {
            return $this->_respondError($e->getMessage());
        }
    }

    protected function _respondError($msg='')
    {
        return [
            'ok' => false,
            'message' => $msg
        ];
    }
}
