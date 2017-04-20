<?php
namespace Tests\Ui;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\WebDriverExpectedCondition;

class HealthProTest extends AbstractPmiUiTestCase
{
    public function testHealthPro()
    {
        $this->login();

        $this->participantLookup();
    }

    public function login()
    {
        $this->webDriver->get($this->baseUrl);

        //Check home page
        $this->assertContains('Error - HealthPro', $this->webDriver->getTitle());

        //Click try again
        $this->webDriver->findElement(WebDriverBy::cssSelector('.container a'))->click();

        //Go to login page
        $this->assertContains('Login', $this->webDriver->getTitle());

        //Enter email
        $email = 'test@example.com';
        $elementEmail = $this->webDriver->findElement(WebDriverBy::name('email'));
        $elementEmail->clear();
        $elementEmail->click();
        $this->webDriver->getKeyboard()->sendKeys($email);

        //Click login
        $this->webDriver->findElement(WebDriverBy::id('submit-login'))->click();
        
        //Click agree
        $this->webDriver->wait(5, 500)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::className('pmi-confirm-ok'))
        );
        $this->webDriver->findElement(WebDriverBy::cssSelector('.pmi-confirm-ok'))->click();
        $this->assertContains('Choose Destination - HealthPro', $this->webDriver->getTitle());
    }

    public function participantLookup()
    {
        //Wait untill modal window completly disappears
        $driver = $this->webDriver;
        $this->webDriver->wait()->until(
            function () use ($driver) {
                $elements = $driver->findElements(WebDriverBy::cssSelector('.modal-open'));
                return count($elements) == 0;
            }
        );

        //Click Healthpro destination
        $this->webDriver->findElement(WebDriverBy::xpath("//a[@href='/']"))->click();
        $this->webDriver->wait()->until(
            WebDriverExpectedCondition::titleContains('Choose Site - HealthPro')
        );

        //Select site Hogwarts
        $select = new WebDriverSelect($this->webDriver->findElement(WebDriverBy::tagName('select')));
        $select->selectByValue('hpo-site-hogwarts@pmi-ops.io');

        //Click continue
        $this->webDriver->findElement(WebDriverBy::className('site-submit'))->click();
        $this->webDriver->wait()->until(
            WebDriverExpectedCondition::titleContains('HealthPro')
        );

        //Go to Workqueue page
        $this->webDriver->findElement(WebDriverBy::xpath("//a[@href='/workqueue/']"))->click();
        $this->webDriver->wait()->until(
            WebDriverExpectedCondition::titleContains('Participant Work Queue - HealthPro')
        );

        //Select patient who's status is not withdrawn
        $elements = $this->webDriver->findElements(WebDriverBy::cssSelector('tbody tr'));
        for ($i = 1; $i <= count($elements); $i++) { 
            $tableCols = $this->webDriver->findElement(WebDriverBy::cssSelector('tbody tr:nth-child('.$i.') td:nth-child(9)'));
            if (empty($tableCols->getText())){
                $lastName = $this->webDriver->findElement(WebDriverBy::cssSelector('tbody tr:nth-child('.$i.') td:nth-child(1)'))->getText();
                $dob = $this->webDriver->findElement(WebDriverBy::cssSelector('tbody tr:nth-child('.$i.') td:nth-child(3)'))->getText();
                if (!empty($dob)) {
                    break;
                }
            }
        }

        //Go to ParticipantsLookup page
        $this->webDriver->findElement(WebDriverBy::xpath("//a[@href='/participants']"))->click();

        //Enter lastname and dob
        $this->webDriver->findElement(WebDriverBy::id('search_lastName'))->click();
        $this->webDriver->getKeyboard()->sendKeys($lastName);
        $this->webDriver->findElement(WebDriverBy::id('search_dob'))->click();
        $this->webDriver->getKeyboard()->sendKeys($dob);

        //Click search
        $this->webDriver->findElement(WebDriverBy::cssSelector('form[name=search] .btn-primary'))->click();
        $body = $this->webDriver->findElement(WebDriverBy::cssSelector('body'))->getText();
        $this->assertContains($lastName, $body);
    }
}
