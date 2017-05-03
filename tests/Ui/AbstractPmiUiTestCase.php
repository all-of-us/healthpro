<?php
namespace Tests\Ui;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\WebDriverExpectedCondition;

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

    public function waitForElementVisible($elt)
    {
        $this->webDriver->wait(15, 500)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated($elt)
        );
    }

    public function waitForIdVisible($id)
    {
        return $this->waitForElementVisible(WebDriverBy::id($id));
    }

    public function waitForClassVisible($class)
    {
        return $this->waitForElementVisible(WebDriverBy::className($class));
    }

    public function waitForPathVisible($path)
    {
        return $this->waitForElementVisible(WebDriverBy::xpath("//a[@href='".$path."']"));
    }

    public function findById($id)
    {
        return $this->webDriver->findElement(WebDriverBy::id($id));
    }

    public function findByName($name)
    {
        return $this->webDriver->findElement(WebDriverBy::name($name));
    }

    public function findBySelector($selector)
    {
        return $this->webDriver->findElement(WebDriverBy::cssSelector($selector));
    }

    public function findByClass($class)
    {
        return $this->webDriver->findElement(WebDriverBy::className($class));
    }

    public function findByTag($tag)
    {
        return $this->webDriver->findElement(WebDriverBy::tagName($tag));
    }

    public function findByXPath($path)
    {
        return $this->webDriver->findElement(WebDriverBy::xpath("//a[@href='".$path."']"));
    }

    public function sendKeys($text)
    {
        $this->webDriver->getKeyboard()->sendKeys($text);
    }

    public function setInput($name, $value)
    {
        $input = $this->findByName($name);
        $input->click();
        $this->sendKeys($value);
    }
}
