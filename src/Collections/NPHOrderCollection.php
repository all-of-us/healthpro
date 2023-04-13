<?php

namespace App\Collections;
use App\Entity\NphOrder;
use ArrayIterator;
use IteratorAggregate;

class NPHOrderCollection extends ArrayIterator
{
    public function __construct(NphOrder ...$orders)
    {
        parent::__construct($orders);
    }
}
