<?php
namespace Navac\Qpi;

use Navac\Qpi\Syntax\ {
    Lexer,
    QueryParser,
    Evaluator
};
use App\Http\Controllers\Controller;
use Navac\Qpi\Support\ParserSyntaxException;

class QueryCtrl extends Controller
{
    public static $userModels = [];

    public function index($query)
    {
        self::$userModels = config('qpi.models');

        try {
            $tokens = Lexer::run($query);
            $parseTree = (new QueryParser($tokens))->parse();
            $result = Evaluator::eval($parseTree);

            return $result;

        } catch (ParserSyntaxException $e) {
            return view('qpi::syntax_error', [
                'source'  => $query,
                'message' => $e->getMessage(),
                'row' => $e->debug['row'],
                'col' => $e->debug['col']
            ]);
        }
    }
}
