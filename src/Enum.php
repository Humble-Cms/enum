<?php

namespace Spatie\Enum;

use BadMethodCallException;
use JsonSerializable;
use ReflectionClass;
use Spatie\Enum\Exceptions\DuplicateLabelsException;
use Spatie\Enum\Exceptions\DuplicateValuesException;
use Spatie\Enum\Exceptions\UnknownEnumMethod;
use Spatie\Enum\Exceptions\UnknownEnumProperty;
use TypeError;

/**
 * @property-read string|int value
 * @property-read string label
 */
abstract class Enum implements JsonSerializable
{
    /** @var string|int */
    protected $value;

    protected string $label;

    private static array $definitionCache = [];

    public static function toArray(): array
    {
        $array = [];

        foreach (static::resolveDefinition() as $definition) {
            $array[$definition->value] = $definition->label;
        }

        return $array;
    }

    /**
     * @param string|int $value
     *
     * @return static
     */
    public static function make($value): Enum
    {
        return new static($value);
    }

    /**
     * @param string|int $value
     *
     * @internal
     */
    public function __construct($value)
    {
        if (! (is_string($value) || is_int($value))) {
            $enumClass = static::class;

            throw new TypeError("Only string and integer are allowed values for enum {$enumClass}.");
        }

        $definition = $this->findDefinition($value);

        if ($definition === null) {
            $enumClass = static::class;

            throw new BadMethodCallException("There's no value {$value} defined for enum {$enumClass}, consider adding it in the docblock definition.");
        }

        $this->value = $definition->value;
        $this->label = $definition->label;
    }

    /**
     * @param string $name
     *
     * @return int|string
     *
     * @throws UnknownEnumProperty
     */
    public function __get(string $name)
    {
        if ($name === 'label') {
            return $this->label;
        }

        if ($name === 'value') {
            return $this->value;
        }

        throw UnknownEnumProperty::new(static::class, $name);
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return static
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return new static($name);
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return bool
     *
     * @throws UnknownEnumMethod
     */
    public function __call(string $name, array $arguments)
    {
        if (strpos($name, 'is') === 0) {
            $other = new static(substr($name, 2));

            return $this->equals($other);
        }

        throw UnknownEnumMethod::new(static::class, $name);
    }

    public function equals(Enum ...$others): bool
    {
        foreach ($others as $other) {
            if (
                get_class($this) === get_class($other)
                && $this->value === $other->value
            ) {
                return true;
            }
        }

        return false;
    }

    protected static function values(): array
    {
        return [];
    }

    protected static function labels(): array
    {
        return [];
    }

    /**
     * @param string|int $input
     *
     * @return \Spatie\Enum\EnumDefinition|null
     */
    private function findDefinition($input): ?EnumDefinition
    {
        foreach (static::resolveDefinition() as $definition) {
            if ($definition->equals($input)) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return \Spatie\Enum\EnumDefinition[]
     */
    private static function resolveDefinition(): array
    {
        $className = static::class;

        if (static::$definitionCache[$className] ?? null) {
            return static::$definitionCache[$className];
        }

        $reflectionClass = new ReflectionClass($className);

        $docComment = $reflectionClass->getDocComment();

        preg_match_all('/@method static self ([\w_]+)\(\)/', $docComment, $matches);

        $definition = [];

        $valueMap = static::values();

        $labelMap = static::labels();

        foreach ($matches[1] as $methodName) {
            $value = $valueMap[$methodName] = $valueMap[$methodName] ?? $methodName;

            $label = $labelMap[$methodName] = $labelMap[$methodName] ?? $methodName;

            $definition[$methodName] = new EnumDefinition($methodName, $value, $label);
        }

        if (self::arrayHasDuplicates($valueMap)) {
            throw new DuplicateValuesException(static::class);
        }

        if (self::arrayHasDuplicates($labelMap)) {
            throw new DuplicateLabelsException(static::class);
        }

        return static::$definitionCache[$className] ??= $definition;
    }

    private static function arrayHasDuplicates(array $array): bool
    {
        return count($array) > count(array_unique($array));
    }

    /**
     * @return int|string
     */
    public function jsonSerialize()
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
