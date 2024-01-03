<?php

namespace App\WorkQueue;

use App\WorkQueue\ColumnDefs\ColumnInterface;
use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * Class ColumnCollection
 * @package App\WorkQueue
 * @implements IteratorAggregate<ColumnInterface>
 */
class ColumnCollection implements IteratorAggregate
{
    /**
     * @var ColumnInterface[] $columns
     */
    private array $columns;
    public function __construct(ColumnInterface ...$columns)
    {
        $this->columns = $columns;
    }
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->columns);
    }
}
