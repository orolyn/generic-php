<?php

namespace Orolyn\GenericPHP;

use Orolyn\GenericPHP\Parser\Tokens;

class Lexer extends \PhpParser\Lexer
{
    private const T_LESS_THAN = 60;
    private const T_GREATER_THAN = 62;
    private const T_COMMA = 44;
    private const T_BACKSLASH = 92;

    private const VALID_TYPE_ARG_TOKENS = [
        self::T_LESS_THAN,
        Tokens::T_NS_SEPARATOR,
        Tokens::T_STRING,
        self::T_COMMA,
        self::T_GREATER_THAN,
        Tokens::T_SR,
    ];

    private $typeArgLevel = 0;

    private $forceNext = null;

    public function __construct(array $options = [])
    {
        parent::__construct($options);
    }

    /**
     * @inheritdoc
     */
    public function getNextToken(&$value = null, &$startAttributes = null, &$endAttributes = null) : int
    {
        if ($token = $this->forceNext) {
            $this->forceNext = null;
        } else {
            $token = parent::getNextToken($value, $startAttributes, $endAttributes);

            if (self::T_LESS_THAN === $token) {
                $position = $this->pos;
                $level = 1;

                for (;;) {
                    $lookahead = parent::getNextToken($value, $startAttributes, $endAttributes);

                    if (!in_array($lookahead, self::VALID_TYPE_ARG_TOKENS)) {
                        break;
                    }

                    if (self::T_LESS_THAN === $lookahead) {
                        $level++;
                    }

                    if (self::T_GREATER_THAN === $lookahead) {
                        $level--;
                    }

                    if (Tokens::T_SR === $lookahead) {
                        $level -= 2;
                    }

                    if ($level <= 0) {
                        $token = Tokens::T_START_TARG;
                        $this->typeArgLevel++;
                        break;
                    }
                }

                $this->pos = $position;
            }

            if (self::T_GREATER_THAN === $token && $this->typeArgLevel > 0) {
                $token = Tokens::T_CLOSE_TARG;
                $this->typeArgLevel--;
            }

            if (Tokens::T_SR === $token && $this->typeArgLevel > 0) {
                $token = Tokens::T_CLOSE_TARG;
                $this->typeArgLevel--;
                $this->forceNext = Tokens::T_CLOSE_TARG;
            }
        }

        return $token;
    }

    /**
     * @inheritdoc
     */
    protected function createTokenMap(): array
    {
        $tokenMap = parent::createTokenMap();

        $tokenMap[] = Tokens::T_START_TARG;
        $tokenMap[] = Tokens::T_CLOSE_TARG;

        return $tokenMap;
    }
}
