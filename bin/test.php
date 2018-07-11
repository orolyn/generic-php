<?php

namespace Test;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\ChainCache;
use Doctrine\Common\Cache\PhpFileCache;
use Orolyn\GenericPHP\ClassName;
use Orolyn\GenericPHP\Lexer;
use Orolyn\GenericPHP\Node\ArgumentedType;
use Orolyn\GenericPHP\Node\GenericType;
use Orolyn\GenericPHP\NodeVisitor\NameResolver;
use Orolyn\GenericPHP\Parser\Php7;
use Orolyn\GenericPHP\Printer;
use PhpParser\Builder\Namespace_;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeDumper;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\FindingVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter\Standard;

require_once __DIR__ . '/../vendor/autoload.php';

$code = <<<'CODE'
<?php

namespace Test;

class HashMap<K, V>
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;    
    }

    public function get(K $key): V
    {
        return $this->value;
    }
    
    public function copy(): HashMap<K, V> 
    {
        return new HashMap<K, V>();
    }
    
    public function createNested(): HashMap<K, HashMap<K, V>>
    {
        return new HashMap<K, HashMap<K, V>>();
    }
}
$b = new HashMap<string, int>(100);

CODE;


$code = <<<'CODE'
<?php

namespace Test;

class A<T>
{
    private $value;

    public function __construct(T $value)
    {
        $this->value = $value;
    }

    public function getValue(): T
    {
        return $this->value;
    }
}

class B<X, Y>
{
    public $x;
    public $y;

    public function __construct(X $x, Y $y)
    {
        $this->x = $x;
        $this->y = $y;    
    }
}

$a = new A(new B<\DateTime>(new \DateTime(), new \stdClass()));
print_r($a);
print_r($a->getValue());

new A<B<\DateTime, \stdClass>>(new B<\DateTime, \stdClass>('wrong argument', new \stdClass())); // B wrong arg

CODE;

$parser = new Php7(new Lexer());
try {
    $ast = $parser->parse($code);
} catch (Error $error) {
    echo "Parse error: {$error->getMessage()}\n";
    return;
}

$nameTraverser = new NodeTraverser();
$nameTraverser->addVisitor(new NameResolver());
$ast = $nameTraverser->traverse($ast);

$cache = new PhpFileCache(__DIR__ . '/../cache/nodes');

$classTraverser = new NodeTraverser();
$classTraverser->addVisitor(
    new class($cache) extends NodeVisitorAbstract
    {
        /**
         * @var Cache
         */
        private $cache;

        public function __construct(Cache $cache)
        {
            $this->cache = $cache;
        }

        public function enterNode(Node $node)
        {
            if ($node instanceof Node\Stmt\Class_ ||
                $node instanceof Node\Stmt\Interface_ ||
                $node instanceof Node\Stmt\Trait_) {

                if ($node->name instanceof GenericType) {
                    $this->cache->save('class_' . $node->namespacedName->toString(), clone $node);
                    $this->sanitizeClass($node);
                }
            }
        }

        private function sanitizeClass(Node\Stmt\ClassLike $node)
        {
            /** @var GenericType $name */
            $name = $node->name;

            $traverser = new NodeTraverser();
            $traverser->addVisitor(
                new class ($this->cache, $name->typeParams) extends NodeVisitorAbstract
                {
                    /**
                     * @var Cache
                     */
                    private $cache;

                    /**
                     * @var array
                     */
                    private $typeParams;

                    /**
                     *  constructor.
                     * @param Cache $cache
                     * @param array $typeParams
                     */
                    public function __construct(Cache $cache, array $typeParams)
                    {
                        $this->cache = $cache;

                        $this->typeParams = array_map(
                            function ($v) {
                                return strtoupper($v);
                            },
                            $typeParams
                        );
                    }

                    public function leaveNode(Node $node)
                    {
                        if ($node instanceof Node\Stmt\ClassMethod) {
                            foreach ($node->params as $param) {
                                if ($this->isTypeParam($param->type)) {
                                    $param->type = null;
                                }
                            }

                            if ($node->returnType) {
                                if ($this->isTypeParam($node->returnType)) {
                                    $node->returnType = null;
                                } elseif ($node->returnType instanceof ArgumentedType) {
                                    $hash = $this->serializeArgumentedType($node->returnType);
                                    $this->cache->save($hash, clone $node);

                                    $node->returnType = new Node\Name\FullyQualified($hash);
                                }
                            }
                        }

                        if ($node instanceof ArgumentedType) {
                            $hash = $this->serializeArgumentedType($node);
                            $this->cache->save($hash, clone $node);

                            return new Node\Name\FullyQualified($hash);
                        }

                        return null;
                    }

                    private function serializeArgumentedType(ArgumentedType $node)
                    {
                        $name = $node->getAttribute('resolvedName')->toString();

                        $typeArgs = [];

                        foreach ($node->typeArgs as $typeArg) {
                            if ($typeArg instanceof Node\Identifier) {
                                $typeArgs[] = $typeArg->toString();
                            } elseif ($typeArg instanceof ArgumentedType) {
                                $typeArgs[] = $this->serializeArgumentedType($typeArg);
                            } elseif ($typeArg instanceof Node\Name) {
                                if ($this->isTypeParam($typeArg)) {
                                    $typeArgs[] = 'void';
                                } elseif ($typeArg instanceof Node\Name\FullyQualified) {
                                    $typeArgs[] = $typeArg->toString();
                                } else {
                                    $typeArgs[] = $typeArg->getAttribute('resolvedName')->toString();
                                }
                            }
                        }

                        if (count(array_unique($typeArgs)) === 1 && 'void' === array_unique($typeArgs)[0]) {
                            return $name;
                        } else {
                            return sprintf('%s\\type_%s', $name, sha1(sprintf('%s<%s>', $name, implode(', ', $typeArgs))));
                        }
                    }

                    private function isTypeParam(Node $node = null)
                    {
                        if (null !== $node && $node instanceof Node\Name) {
                            return count($node->parts) === 1 && in_array(strtoupper($node->parts[0]), $this->typeParams);
                        }

                        return false;
                    }
                }
            );
            $node->stmts = $traverser->traverse($node->stmts);
        }
    }
);
$ast = $classTraverser->traverse($ast);

$traverser = new NodeTraverser();
$traverser->addVisitor(
    new class ($cache) extends NodeVisitorAbstract
    {
        /**
         * @var Cache
         */
        private $cache;

        public function __construct(Cache $cache)
        {
            $this->cache = $cache;
        }

        public function enterNode(Node $node)
        {
            if ($node instanceof ArgumentedType) {
                $hash = $this->serializeArgumentedType($node);
                $this->cache->save($hash, clone $node);

                return new Node\Name\FullyQualified($hash);
            }
        }

        private function serializeArgumentedType(ArgumentedType $node)
        {
            $name = $node->getAttribute('resolvedName')->toString();

            $typeArgs = [];

            foreach ($node->typeArgs as $typeArg) {
                if ($typeArg instanceof Node\Identifier) {
                    $typeArgs[] = $typeArg->toString();
                } elseif ($typeArg instanceof ArgumentedType) {
                    $typeArgs[] = $this->serializeArgumentedType($typeArg);
                } elseif ($typeArg instanceof Node\Name) {
                    $typeArgs[] = $typeArg->getAttribute('resolvedName')->toString();
                }
            }

            if (count(array_unique($typeArgs)) === 1 && 'void' === array_unique($typeArgs)[0]) {
                return $name;
            } else {
                return sprintf('%s\\type_%s', $name, sha1(sprintf('%s<%s>', $name, implode(', ', $typeArgs))));
            }
        }
    }
);
$traverser->traverse($ast);

$dumper = new NodeDumper;
//echo $dumper->dump($ast) . "\n";

echo "\n";

$printer = new Printer();
$output = $printer->prettyPrintFile($ast);

$phpfile = __DIR__ . '/../cache/classes/' . sha1($output);
@mkdir(dirname($phpfile));

function render_template(Node\Stmt\ClassLike $template, ArgumentedType $type, string $class): string
{
    global $cache;

    $traverser = new NodeTraverser();
    $traverser->addVisitor(
        new class ($cache, $type, $class) extends NodeVisitorAbstract
        {
            /**
             * @var Cache
             */
            private $cache;

            /**
             * @var array
             */
            private $typeParams;

            /**
             * @var ArgumentedType
             */
            private $type;

            /**
             * @var string
             */
            private $class;

            /**
             *  constructor.
             * @param ArgumentedType $type
             */
            public function __construct(Cache $cache, ArgumentedType $type, string $class)
            {
                $this->cache = $cache;
                $this->type = $type;
                $this->class = $class;
            }

            /**
             * @param Node $node
             */
            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\ClassLike) {
                    /** @var GenericType $name */
                    $name = $node->name;

                    foreach ($name->typeParams as $i => $typeParam) {
                        $this->typeParams[$typeParam] = $this->type->typeArgs[$i];
                    }

                    $fullName = new Node\Name($this->serializeArgumentedType($this->type));

                    $node->name = new Node\Identifier(end($fullName->parts));
                    $node->namespacedName = $fullName;
                }

                if ($node instanceof Node\Stmt\ClassMethod) {
                    foreach ($node->params as $param) {
                        if ($this->isTypeParam($param->type)) {
                            $param->type = $this->typeParams[$param->type->toString()];
                        }
                    }

                    if ($node->returnType) {
                        if ($this->isTypeParam($node->returnType)) {
                            $node->returnType = $this->typeParams[$node->returnType->toString()];
                        } elseif ($node->returnType instanceof ArgumentedType) {
                            $this->resolveArgumentedType($node->returnType);
                            $hash = $this->serializeArgumentedType($node->returnType);
                            $this->cache->save($hash, $node->returnType);

                            $node->returnType = new Node\Name\FullyQualified($hash);
                        }
                    }

                }

                if ($node instanceof ArgumentedType) {
                    $this->resolveArgumentedType($node);
                    $hash = $this->serializeArgumentedType($node);
                    $this->cache->save($hash, clone $node);

                    return new Node\Name\FullyQualified($hash);
                }
            }

            private function resolveArgumentedType(ArgumentedType $type)
            {
                foreach ($type->typeArgs as $i => $typeArg) {
                    if ($this->isTypeParam($typeArg)) {
                        $type->typeArgs[$i] = $this->typeParams[$typeArg->toString()];
                    } elseif ($typeArg instanceof ArgumentedType) {
                        $this->resolveArgumentedType($typeArg);
                        $hash = $this->serializeArgumentedType($typeArg);
                        $this->cache->save($hash, $typeArg);
                    }
                }
            }

            private function serializeArgumentedType(ArgumentedType $node)
            {
                $name = $node->getAttribute('resolvedName')->toString();

                $typeArgs = [];

                foreach ($node->typeArgs as $typeArg) {
                    if ($typeArg instanceof Node\Identifier) {
                        $typeArgs[] = $typeArg->toString();
                    } elseif ($typeArg instanceof ArgumentedType) {
                        $typeArgs[] = $this->serializeArgumentedType($typeArg);
                    } elseif ($typeArg instanceof Node\Name) {
                        if ($this->isTypeParam($typeArg)) {
                            $typeArgs[] = $this->typeParams[$typeArg->toString()]->toString();
                        } elseif ($typeArg instanceof Node\Name\FullyQualified) {
                            $typeArgs[] = $typeArg->toString();
                        } else {
                            $typeArgs[] = $typeArg->getAttribute('resolvedName')->toString();
                        }
                    }
                }

                if (count(array_unique($typeArgs)) === 1 && 'void' === array_unique($typeArgs)[0]) {
                    return $name;
                } else {
                    return sprintf('%s\\type_%s', $name, sha1(sprintf('%s<%s>', $name, implode(', ', $typeArgs))));
                }
            }

            private function isTypeParam(Node $node = null)
            {
                if (null !== $node && $node instanceof Node\Name) {
                    return count($node->parts) === 1 && array_key_exists(strtoupper($node->parts[0]), $this->typeParams);
                }

                return false;
            }
        }
    );
    /** @var Node\Stmt\ClassLike $ast */
    $ast = $traverser->traverse([$template]);

    $namespaceName = clone ($ast[0]->namespacedName);
    $filename = __DIR__ . '/../cache/classes/' . sha1($namespaceName);
    array_pop($namespaceName->parts);

    $builder = new Namespace_($namespaceName);
    $builder->addStmt($ast[0]);

    $printer = new Printer();
    $output = $printer->prettyPrintFile([$builder->getNode()]);
    file_put_contents($filename, $output);

    //echo $output;

    return $filename;
}

spl_autoload_register(
    function ($class) use ($cache) {
        /** @var ArgumentedType $instance */
        if ($type = $cache->fetch($class)) {
            if ($template = $cache->fetch('class_' . $type->getAttribute('resolvedName')->toString())) {
                require_once render_template($template, $type, $class);
            }
        }
    }
);

file_put_contents($phpfile, $output);

require_once $phpfile;

//var_dump($a->createNested('sample'));
//var_dump($b->get('sample'));

//$a->doSomething(1);

echo "\n\n";
