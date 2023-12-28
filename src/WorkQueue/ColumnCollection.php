<?php

namespace App\WorkQueue;

use App\WorkQueue\ColumnDefs\columnInterface;
use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * Class ColumnCollection
 * @package App\WorkQueue
 * @implements IteratorAggregate<columnInterface>
 */
class ColumnCollection implements IteratorAggregate
{
    /**
     * @var columnInterface[] $columns
     */
    private array $columns;
    public function __construct(columnInterface ...$columns)
    {
        $this->columns = $columns;
    }
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->columns);
    }
}
