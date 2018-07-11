<?php
namespace Orolyn\GenericPHP\Node;

use PhpParser\Node\Name;

class ArgumentedType extends Name
{
    public $name;

    /**
     * @var array
     */
    public $typeArgs;

    /**
     * ArgumentedType constructor.
     * @param Name $name
     * @param array $typeArgs
     * @param array $attributes
     */
    public function __construct(Name $name, array $typeArgs = [], array $attributes = [])
    {
        $this->setName($name);

        $this->typeArgs = $typeArgs;
    }

    public function setName(Name $name)
    {
        $this->name = $name;
        $this->parts = &$name->parts;
        $this->attributes = $name->attributes;
    }

    /**
     * @inheritdoc
     */
    public function getSubNodeNames(): array
    {
        return ['parts', 'typeArgs'];
    }

    /**
     * @inheritdoc
     */
    public function getType(): string
    {
        return 'ArgumentedType';
    }

    /**
     * Gets the first part of the name, i.e. everything before the first namespace separator.
     *
     * @return string First part of the name
     */
    public function getFirst() : string
    {
        return $this->name->getFirst();
    }

    /**
     * Gets the last part of the name, i.e. everything after the last namespace separator.
     *
     * @return string Last part of the name
     */
    public function getLast() : string
    {
        return $this->name->getLast();
    }

    /**
     * Checks whether the name is unqualified. (E.g. Name)
     *
     * @return bool Whether the name is unqualified
     */
    public function isUnqualified() : bool
    {
        return $this->name->isUnqualified();
    }

    /**
     * Checks whether the name is qualified. (E.g. Name\Name)
     *
     * @return bool Whether the name is qualified
     */
    public function isQualified() : bool
    {
        return $this->name->isQualified();
    }

    /**
     * Checks whether the name is fully qualified. (E.g. \Name)
     *
     * @return bool Whether the name is fully qualified
     */
    public function isFullyQualified() : bool
    {
        return $this->name->isFullyQualified();
    }

    /**
     * Checks whether the name is explicitly relative to the current namespace. (E.g. namespace\Name)
     *
     * @return bool Whether the name is relative
     */
    public function isRelative() : bool
    {
        return $this->name->isRelative();
    }

    /**
     * Returns a string representation of the name itself, without taking taking the name type into
     * account (e.g., not including a leading backslash for fully qualified names).
     *
     * @return string String representation
     */
    public function toString() : string
    {
        return $this->name->toString();
    }

    /**
     * Returns a string representation of the name as it would occur in code (e.g., including
     * leading backslash for fully qualified names.
     *
     * @return string String representation
     */
    public function toCodeString() : string
    {
        return $this->name->toCodeString();
    }

    /**
     * Returns lowercased string representation of the name, without taking the name type into
     * account (e.g., no leading backslash for fully qualified names).
     *
     * @return string Lowercased string representation
     */
    public function toLowerString() : string
    {
        return $this->name->toLowerString();
    }

    /**
     * Checks whether the identifier is a special class name (self, parent or static).
     *
     * @return bool Whether identifier is a special class name
     */
    public function isSpecialClassName() : bool
    {
        return false;
    }

    /**
     * Returns a string representation of the name by imploding the namespace parts with the
     * namespace separator.
     *
     * @return string String representation
     */
    public function __toString() : string
    {
        return $this->name->__toString();
    }

    /**
     * Gets a slice of a name (similar to array_slice).
     *
     * This method returns a new instance of the same type as the original and with the same
     * attributes.
     *
     * If the slice is empty, null is returned. The null value will be correctly handled in
     * concatenations using concat().
     *
     * Offset and length have the same meaning as in array_slice().
     *
     * @param int      $offset Offset to start the slice at (may be negative)
     * @param int|null $length Length of the slice (may be negative)
     *
     * @return static|null Sliced name
     */
    public function slice(int $offset, int $length = null)
    {
        return new static($this->name->slice($offset, $length), $this->typeArgs, $this->attributes);
    }
}
