<?php
namespace Navac\Qpi\Syntax;

use Navac\Qpi\Syntax\Util\ {
    Model,
    Field,
    WhereStatement
};

class QueryParser extends Parser
{
    public function __construct(array $tokens = [])
    {
        parent::__construct($tokens);
    }

    public function parse()
    {
        $this->_status = null;

        while ($this->_isTokenExists()) {
            $curToken = $this->getCurToken();

            switch ($curToken['token']) {
                case 'T_BLOCK_START':
                    $this->_status ='MODEL_BLOCK_STARTED';
                    $this->peek();

                    array_push($this->_parseTree, $this->parseModel());
                    break;

                case 'T_BLOCK_END':
                    $this->_status ='MODEL_BLOCK_CLOSED';
                    $this->peek();
                    break;

                case 'T_BRACKET_START':
                    end($this->_parseTree)->setWhereStats($this->parseWhereClause());
                    break;

                default:
                    throw new \Exception("In parse => Unexpected token: " . $curToken['match']);
                    break;
            }
        }

        switch ($this->_status) {
            case 'MODEL_BLOCK_STARTED':
                throw new \Exception('Expected token: }');
                break;

            default:
                // everything is ok :)
                break;
        }

        return $this->_parseTree;
    }

    protected function parseModel()
    {
        $modelParseComplete = false;

        $model = null;

        while (!$modelParseComplete) {
            $curToken = $this->getCurToken();

            switch($curToken['token']) {
                case 'T_DASH':
                    $this->peek();
                    break;

                case 'T_IDENTIFIER':
                    // if we does not have a model, so it is a model-identifier
                    if(!$model) { $model = new Model($curToken['match']); $this->peek(); }
                    // if we have a model, so this identifer is a field-identifier
                    else $model->setFields($this->parseFields());
                    break;


                // @TODO add ability to expect tokens when limit or offset does not exsists
                case 'T_PARENTHES_START':
                    $expectedTokens = ['T_PARENTHES_START', 'T_DIGIT', 'T_COLON', 'T_DIGIT', 'T_PARENTHES_END'];
                    foreach($this->jumpTo(5) as $key => $token) {
                        if($token['token'] !== $expectedTokens[$key]) throw new \Exception("In limits => Limits syntax is not currect.");
                    }

                    $limits = $this->parseLimits();
                    $model->setLimits($limits['offset'], $limits['limit']);
                    break;

                case 'T_BLOCK_START':
                    if(!$model) throw new \Exception("You have to identify your model.");
                    $this->peek();
                    break;

                case 'T_BLOCK_END':
                    $modelParseComplete = true;
                    break;

                default:
                    return $model;
            }
        }

        return $model;
    }

    protected function parseFields()
    {
        $fieldsParseComplete = false;

        $fields = [];

        while (!$fieldsParseComplete) {
            $curToken = $this->getCurToken();

            switch($curToken['token']) {
                case 'T_DASH':
                case 'T_COLON':
                case 'T_DIGIT':
                    $this->peek();
                    break;

                case 'T_IDENTIFIER':
                    // If next token is T_BLOCK_START, look ahead and parse it as a model
                    if(
                        $this->nextToken()['token'] === 'T_BLOCK_START' ||
                        $this->nextToken()['token'] === 'T_PARENTHES_START'
                    ) { array_push($fields, $this->parseModel()); }

                    // if not, parse it as a normal field
                    else {
                        $field = new Field();
                        $field->name = $curToken['match'];
                        $field->isInGroupBy = $this->prevToken()['token'] === 'T_DASH';
                        $field->isInOrderBy = $this->parseOrderBy();

                        array_push($fields, $field);
                    }

                    $this->peek();
                    break;

                case 'T_COMMA':
                    $this->peek();
                    break;

                default:
                    return $fields;
            }
        }

        return $fields;
    }

    protected function parseOrderBy()
    {
        if($this->nextToken()['token'] === 'T_COLON') {
            $afterColon = $this->jumpTo(3)[2];

            if($afterColon['token'] !== 'T_DIGIT') {
                throw new \Exception('Only T_DIGIT expected after orderBy colon.');
                return;
            }

            if($afterColon['match'] !== 0 && $afterColon['match'] !== 1) {
                throw new \Exception('Only 1 or 0 are acceptable for orderBy.');
                return;
            }

            return $afterColon['match'] === 1 ? 'ASC' : 'DESC';
        }

        return false;
    }

    protected function parseLimits() : array
    {
        $limitsParseComplete = false;

        $offsetDetected = false;
        $limits = ['limit' => 10, 'offset' => 0];

        while (!$limitsParseComplete) {
            $curToken = $this->getCurToken();

            switch ($curToken['token']) {
                case 'T_PARENTHES_START':
                    $this->peek();
                    break;

                case 'T_PARENTHES_END':
                    $this->peek();
                    $limitsParseComplete = true;
                    break;

                case 'T_DIGIT':
                    if($offsetDetected) $limits['limit'] = (int) $curToken['match'];
                    else $limits['offset'] = (int) $curToken['match'];
                    $this->peek();
                    break;

                case 'T_COLON':
                    $offsetDetected = true;
                    $this->peek();
                    break;

                default:
                    return $limits;
            }
        }

        return $limits;
    }

    public function parseWhereClause()
    {
        $whereClauseParseComplete = false;

        $whereStats = [];
        $boolean = '&';

        while ( ! $whereClauseParseComplete) {
            $curToken = $this->getCurToken();

            switch ($curToken['token']) {
                case 'T_BRACKET_START':
                    $this->peek();
                    break;

                case 'T_PARENTHES_START':
                    $this->peek();

                    $whereStats[] = new WhereStatement();
                    $stat = end($whereStats);
                    $stat->type = 'Nested';
                    $stat->boolean = $boolean;
                    $stat->query = $this->parseWhereClause();
                    break;

                case 'T_IDENTIFIER':
                    $whereStats[] = new WhereStatement();
                    end($whereStats)->col = $curToken['match'];
                    $this->peek();
                    break;

                case 'T_OPERATOR':
                    if($curToken['match'] === '&' || $curToken['match'] === '|') {
                        $boolean = $curToken['match'];
                    } else {
                        end($whereStats)->operator = $curToken['match'];
                        end($whereStats)->boolean = $boolean;
                    }
                    $this->peek();
                    break;

                case 'T_DIGIT':
                case 'T_STRING_SINGLEQ':
                case 'T_STRING_DOUBLEQ':
                    end($whereStats)->val = $curToken['match'];
                    $this->peek();
                    break;

                case 'T_COMMA':
                case 'T_BRACKET_END':
                    $this->peek();
                    break;

                case 'T_PARENTHES_END':
                    $this->peek();
                    return $whereStats;

                default:
                    return $whereStats;
            }
        }

        return $whereStats;
    }
}
