<?php

namespace App\WorkQueue\DataSources;

use App\WorkQueue\ColumnCollection;

class TestDataSource implements WorkqueueDatasource
{
    public function getWorkqueueData(int $offset, int $limit, ColumnCollection $columInfo): array
    {
        return [['TestField1' => 'TestValue1', 'TestField2' => 'TestValue2', 'TestField3' => 'TestValue3'],
                ['TestField1' => 'TestValue4', 'TestField2' => 'TestValue5', 'TestField3' => 'TestValue6'],
                ['TestField1' => 'TestValue7', 'TestField2' => 'TestValue8', 'TestField3' => 'TestValue9'],
                ['TestField1' => 'TestValue10', 'TestField2' => 'TestValue11', 'TestField3' => 'TestValue12'],
                ['TestField1' => 'TestValue13', 'TestField2' => 'TestValue14', 'TestField3' => 'TestValue15']];
    }

    public function hasMoreResults(): bool
    {
        return false;
    }

    //Todo: Remove before production merge.
    public function rawQuery($query)
    {
        // TODO: Implement rawQuery() method.
    }
}
