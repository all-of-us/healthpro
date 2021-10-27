<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;

use App\Service\TimezoneService;

class TimezoneServiceTest extends TestCase
{
    protected $tsService;

    public function setup(): void
    {
        $this->tsService = new TimezoneService;
    }

    public function testTimezoneDisplay()
    {
        $this->assertEquals('Central Time', $this->tsService->getTimezoneDisplay('America/Chicago'));
        $this->assertEquals('Mountain Time', $this->tsService->getTimezoneDisplay('America/Denver'));
        $this->assertEquals('America/Bogota', $this->tsService->getTimezoneDisplay('America/Bogota'));
    }
}
