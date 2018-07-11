<?php
namespace Orolyn\GenericPHP\Node;

use PhpParser\Node\Identifier;

class GenericType extends Identifier
{
    /**
     * @var Identifier
     */
    public $identifier;

    /**
     * @var array
     */
    public $typeParams;

    /**
     * GenericType constructor.
     * @param Identifier $identifier
     * @param array $typeParams
     */
    public function __construct(Identifier $identifier, array $typeParams = [])
    {
        parent::__construct($identifier->name, $identifier->attributes);

        $this->identifier = $identifier;
        $this->typeParams = $typeParams;
    }

    /**
     * @inheritdoc
     */
    public function getSubNodeNames(): array
    {
        return ['name', 'typeParams'];
    }

    /**
     * @inheritdoc
     */
    public function getType(): string
    {
        return 'GenericType';
    }

    /**
     * Get identifier as string.
     *
     * @return string Identifier as string.
     */
    public function toString() : string {
        return $this->identifier->toString();
    }

    /**
     * Get lowercased identifier as string.
     *
     * @return string Lowercased identifier as string
     */
    public function toLowerString() : string {
        return $this->identifier->toLowerString();
    }

    /**
     * Checks whether the identifier is a special class name (self, parent or static).
     *
     * @return bool Whether identifier is a special class name
     */
    public function isSpecialClassName() : bool {
        return false;
    }

    /**
     * Get identifier as string.
     *
     * @return string Identifier as string
     */
    public function __toString() : string {
        return $this->identifier->__toString();
    }
}
