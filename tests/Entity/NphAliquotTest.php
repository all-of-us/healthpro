<?php

namespace App\Tests\Entity;

use App\Entity\NphAliquot;
use PHPUnit\Framework\TestCase;

class NphAliquotTest extends TestCase
{
    public function testGetAliquotMetadataNormalizesNullFromHydration(): void
    {
        $aliquot = new NphAliquot();
        $property = new \ReflectionProperty(NphAliquot::class, 'aliquotMetadata');
        $property->setValue($aliquot, null);

        $this->assertSame([], $aliquot->getAliquotMetadata());
    }
}
