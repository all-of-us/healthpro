<?php

namespace App\Collections;
use App\Entity\NphOrder;
use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;

class NPHOrderCollection implements ArrayAccess, IteratorAggregate, Countable
{
    private array $orders;

    public function __construct(array $orders)
    {
        $this->orders = $orders;
    }
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->orders);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->orders[$offset]);
    }

    public function offsetGet($offset): NphOrder
    {
        return $this->orders[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->orders[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->orders[$offset]);
    }

    public function count(): int
    {
        return count($this->orders);
    }

    public function append(NphOrder $order): void
    {
        $this->orders[] = $order;
    }
}
