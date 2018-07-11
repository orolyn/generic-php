<?php
namespace Orolyn\GenericPHP\NodeVisitor;

use Orolyn\GenericPHP\Node\ArgumentedType;
use Orolyn\GenericPHP\Node\GenericType;
use PhpParser\ErrorHandler;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;

class NameResolver extends \PhpParser\NodeVisitor\NameResolver
{
    /**
     * @var array
     */
    private $activeTypeParams;

    /**
     * NameResolver constructor.
     * @param null $errorHandler
     * @param array $options
     */
    public function __construct($errorHandler = null, array $options = [])
    {
        parent::__construct($errorHandler, ['replaceNodes' => false]);
    }

    /**
     * @inheritdoc
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassLike) {
            if ($node->name instanceof GenericType) {
                $this->activeTypeParams = $node->name->typeParams;
            }
        }

        return parent::enterNode($node);
    }

    protected function resolveClassName(Name $name) {
        if ($name instanceof ArgumentedType) {
            foreach ($name->typeArgs as $i => $typeArg) {
                if ($typeArg instanceof ArgumentedType) {
                    $name->typeArgs[$i] = $this->resolveClassName($typeArg);
                } elseif ($typeArg instanceof Name && !in_array($typeArg->toString(), $this->activeTypeParams)) {
                    $name->typeArgs[$i] = $this->resolveName($typeArg, Use_::TYPE_NORMAL);
                }
            }
        }

        return $this->resolveName($name, Use_::TYPE_NORMAL);
    }
}
