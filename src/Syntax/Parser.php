<?php
namespace Navac\Qpi\Syntax;

/**
 * 
 */
 abstract class Parser
 {
     /**
      * The tokens to parse
      *
      * @var array
      */
     protected $_tokens = [];

     /**
      * Whitch token our parser currently is pointing?
      *
      * @var integer
      */
     protected $_pointer = 0;

     /**
      * Output! A tree that created after parsing
      *
      * @var array
      */
     protected $_parseTree = [];

     /**
      * What is our parser's status currently?
      *
      * @example The parser expects a specific token but the given token
      * is diffrent. So it should throw and exceptions.
      *
      * @var string
      */
     protected $_status;


     public function __construct(array $tokens = [])
     {
         $this->_tokens = $tokens;
         $this->_tokens = array_values($this->skipWhiteSpaces());
     }

     /**
      * Remove whitespaces from tokens list
      *
      * @return array The tokens w/o whitespaces
      */
     public function skipWhiteSpaces()
     {
         return array_filter($this->_tokens, function ($token) {
             return $token['token'] !== 'T_WHITESPACE';
         });
     }

     /**
      * Get next token.
      *
      * @note It doesn't change the pointer.
      * @return array
      */
     public function nextToken()
     {
        return $this->_tokens[$this->_pointer + 1];
     }

     /**
      * Get previous token.
      *
      * @note It doesn't change the pointer.
      * @return array
      */
     public function prevToken()
     {
        return $this->_tokens[$this->_pointer - 1];
     }

     /**
      * Get current token
      *
      * @return array|bool
      */
     public function getCurToken()
     {
         if($this->_isTokenExists()) {
             return $this->_tokens[$this->_pointer];
         }

         return false;
     }

     /**
      * Moves pointer to next token.
      *
      * @return array|bool
      */
     public function peek()
     {
         $this->_pointer++;
         return $this->getCurToken();
     }

     /**
      * Get a range of tokens between pointer and passed index.
      *
      * @example
      *
      * @param  integer $to
      * @return array
      */
     public function jumpTo($to)
     {
         return array_slice($this->_tokens, $this->_pointer, $to);
     }

     /**
      * Is There Any Other Tokens?
      *
      * @return bool
      */
     protected function _isThereAnyOtherTokens() : bool
     {
         return array_key_exists($this->_pointer + 1, $this->_tokens);
     }

     protected function _isTokenExists() : bool
     {
         return array_key_exists($this->_pointer, $this->_tokens);
     }

     abstract public function parse();
 }
