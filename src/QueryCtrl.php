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
            $tokens = Lexer::run(urldecode($query));
            $parseTree = (new QueryParser($tokens))->parse();
            $result = Evaluator::eval($parseTree);

            return $result;

        } catch (ParserSyntaxException $e) {
            return $e->getMessage();
        }
    }

    public function schema($output='json')
    {
        $models = config('qpi.models');
        $info = [];

        foreach ($models as $modelAlias => $model) {
            array_push($info, [
                'name' => $modelAlias,
                'props' => property_exists($model, 'qpiProps') ? $model::$qpiProps : [],
                'relations' => property_exists($model, 'qpiRelations') ? $model::$qpiRelations : []
            ]);
        }

        if($output === 'html') {
            return view('qpi::schema', [
                'info' => $info
            ]);
        }

        return ['models' => $info];
    }
}
