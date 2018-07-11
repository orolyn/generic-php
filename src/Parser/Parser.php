<?php
namespace Orolyn\GenericPHP\Parser;

use Orolyn\GenericPHP\Node\ArgumentedType;
use Orolyn\GenericPHP\Node\GenericType;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\ParserAbstract;

abstract class Parser extends ParserAbstract
{
    protected $classes = [];

    protected function handleArgumentedType(Node $type, array $typeArgs = [])
    {
        if ($type instanceof Name && count($typeArgs) > 0) {
            $type = new ArgumentedType($type, $typeArgs, $type->getAttributes());
        }

        return $type;
    }

    protected function handleGenericType(Node $type, array $typeParams = [])
    {
        if ($type instanceof Node\Identifier && count($typeParams) > 0) {
            $type = new GenericType($type, $typeParams);
        }

        return $type;
    }
}
