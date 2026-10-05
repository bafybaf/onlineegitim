<?php

declare(strict_types=1);

namespace Sipay\Utils;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use ReturnTypeWillChange;

class CaseInsensitiveArray implements ArrayAccess, Countable, IteratorAggregate
{
    private $container;

    public function __construct($initial_array = [])
    {
        $this->container = \array_change_key_case($initial_array, \CASE_LOWER);
    }

    public function count(): int
    {
        return \count($this->container);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->container);
    }

    public function offsetSet($offset, $value): void
    {
        $offset = self::maybeLowercase($offset);
        if (null === $offset) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        $offset = self::maybeLowercase($offset);

        return isset($this->container[$offset]);
    }

    public function offsetUnset($offset): void
    {
        $offset = self::maybeLowercase($offset);
        unset($this->container[$offset]);
    }

    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        $offset = self::maybeLowercase($offset);

        return $this->container[$offset] ?? null;
    }

    private static function maybeLowercase($v)
    {
        if (\is_string($v)) {
            return \strtolower($v);
        }

        return $v;
    }
}
