<?php
namespace Navac\Qpi\Syntax;

use Navac\Qpi\Support\ParserSyntaxException;

/**
 * [$_terminals description]
 * @var [type]
 */
class Lexer
{
    /**
     * The tokens mappings for the lexer
     *
     * @var array
     */
    protected static $_terminals = [
        "/^(:)/" => "T_COLON",
        "/^(\()/" => "T_PARENTHES_START",
        "/^(\))/" => "T_PARENTHES_END",

        "/^(\d+)/" => "T_DIGIT",
        "/^(\w+)/" => "T_IDENTIFIER",

        "/^(\{)/" => "T_BLOCK_START",
        "/^(\})/" => "T_BLOCK_END",
        "/^(,)/" => "T_COMMA",
        "/^(-)/" => "T_DASH",

        "/^(\[)/" => "T_BRACKET_START",
        "/^(\])/" => "T_BRACKET_END",
        "/^([<|>|~|=|!|&|\|])/" => "T_OPERATOR",

        "/^('.*?')/" => "T_STRING_SINGLEQ",
        "/^(\".*?\")/" => "T_STRING_DOUBLEQ",

        "/^(\s+)/" => "T_WHITESPACE",
    ];

    /**
     * Moves through the source and detects tokens
     * @param  [type] $source [description]
     * @return [type]         [description]
     */
    public static function run($source) {
        $tokens = [];

        $source = explode("\n", $source);

        foreach ($source as $number => $line) {
            $offset = 0;
            while ($offset < strlen($line)) {
                $result = static::_match($line, $number, $offset);
                if ($result === false) {
                    $debug = [
                        'col' => $offset,
                        'row' => $line
                    ];

                    throw new ParserSyntaxException("Unable to parse line " . ($line+1) . ".", $debug);
                }
                $tokens[] = $result;
                $offset += strlen($result['match']);
            }
        }

        return static::convertTypes($tokens);
    }

    /**
     * Matches source with patterns
     *
     * @param  [type] $line   [description]
     * @param  [type] $number [description]
     * @param  [type] $offset [description]
     * @return [type]         [description]
     */
    protected static function _match($line, $number, $offset) {
        $string = substr($line, $offset);

        foreach (static::$_terminals as $pattern => $name) {
            if(preg_match($pattern, $string, $matches)) {
                $m = $matches[1];

                return [
                    'match' => $m,
                    'token' => $name,
                    'line' => $number+1,
                    'offset' => $offset
                ];
            }
        }

        return false;
    }

    /**
     * Detect tokens with specific types and convert them to their related type
     *
     * @param  [type] $tokens [description]
     * @return [type]         [description]
     */
    protected static function convertTypes($tokens)
    {
        foreach($tokens as &$token) {
            switch ($token['token']) {
                case 'T_DIGIT':
                    $token['match'] = (int) $token['match'];
                    break;

                case 'T_OPERATOR':
                    if($token['match'] === '~') {
                        $token['match'] = 'like';
                    }
                    break;

                case 'T_STRING_SINGLEQ':
                case 'T_STRING_DOUBLEQ':
                    // remove first char
                    $token['match'] = substr($token['match'], 1);
                    // remove last char
                    $token['match'] = substr($token['match'], 0, -1);
                    break;
            }
        }

        return $tokens;
    }
}
