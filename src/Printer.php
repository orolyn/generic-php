<?php
namespace Orolyn\GenericPHP;

use Orolyn\GenericPHP\Node\ArgumentedType;
use Orolyn\GenericPHP\Node\GenericType;
use PhpParser\PrettyPrinter\Standard;

class Printer extends Standard
{
    protected function pArgumentedType(ArgumentedType $node) {
        return implode('\\', $node->parts);
    }

    protected function pGenericType(GenericType $node) {
        return $node->name;
    }
}
