<?php
namespace Tests\Ui;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;

abstract class AbstractPmiUiTestCase extends \PHPUnit_Framework_TestCase
{
    private $webDriverUrl = 'http://localhost:4444/wd/hub';
    protected $baseUrl = 'http://localhost:8080';
    protected $webDriver;

    public function setUp()
    {
        $this->webDriver = RemoteWebDriver::create($this->webDriverUrl, [
            WebDriverCapabilityType::BROWSER_NAME => 'chrome'
        ]);
    }

    public function tearDown()
    {
        if ($this->webDriver instanceof RemoteWebDriver) {
            $this->webDriver->quit();
        }
    }
}
