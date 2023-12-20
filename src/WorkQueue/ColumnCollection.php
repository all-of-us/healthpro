<?php

namespace App\WorkQueue;

use App\WorkQueue\ColumnDefs\columnInterface;

class ColumnCollection implements \IteratorAggregate
{
    private array $columns;
    public function __construct(columnInterface ...$columns)
    {
        $this->columns = $columns;
    }
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->columns);
    }
}
