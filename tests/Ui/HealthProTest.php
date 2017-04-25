<?php
namespace Tests\Ui;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\WebDriverExpectedCondition;

class HealthProTest extends AbstractPmiUiTestCase
{
    private $pmiId;
    private $createdDate;

    public function testHealthPro()
    {
        $this->login();

        $this->participantLookup();

        $this->createPhysicalMeasurements();

        $this->checkFinalizedPM();
    }

    public function login()
    {
        $this->webDriver->get($this->baseUrl);

        //Check base page
        $this->assertContains('Error - HealthPro', $this->webDriver->getTitle());

        //Click try again
        $this->findBySelector('.container a')->click();

        //Enter email
        $email = 'test@example.com';
        $this->findByName('email')->clear();
        $this->setInput('email', $email);

        //Click login
        $this->findById('submit-login')->click();

        //Make mouse movement to trigger keepalive ajax call
        $element = $this->findByClass('modal-header');
        $this->webDriver->getMouse()->mouseMove($element->getCoordinates());
        
        //Click agree
        $this->waitForClassVisible('pmi-confirm-ok');
        $this->findBySelector('.pmi-confirm-ok')->click();

        //Check home page
        $this->assertContains('Choose Destination - HealthPro', $this->webDriver->getTitle());
    }

    public function participantLookup()
    {
        //Wait untill modal window completly disappears
        $driver = $this->webDriver;
        $this->webDriver->wait(5, 500)->until(
            function () use ($driver) {
                $elements = $driver->findElements(WebDriverBy::cssSelector('.modal-open'));
                return count($elements) == 0;
            }
        );

        //Click Healthpro destination
        $this->findByXPath('/')->click();

        //Select site
        $select = new WebDriverSelect($this->findByTag('select'));
        $select->selectByValue('hpo-site-hogwarts@pmi-ops.io');

        //Click continue
        $this->findByClass('site-submit')->submit();

        //Go to Workqueue page
        $this->webDriver->get($this->baseUrl.'/workqueue?test='.time());

        //Select patient who's status is not withdrawn, completed basic survery and doesn't have a PM
        $elements = $this->webDriver->findElements(WebDriverBy::cssSelector('tbody tr'));
        for ($i = 1; $i <= count($elements); $i++) { 
            $withdrawn = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(9)')->getText();
            $basicsDate = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(17)')->getText();
            $pmDate = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(30)')->getAttribute('data-order');
            if (empty($withdrawn) && !empty($basicsDate) && $pmDate == '0-') {
                $lastName = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(1)')->getText();
                $dob = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(3)')->getText();
                $this->pmiId = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(4)')->getText();
                if (!empty($dob)) {
                    break;
                }
            }
        }

        //Go to ParticipantsLookup page
        $this->findByXPath('/participants')->click();

        //Enter lastname and dob
        $this->findById('search_lastName')->click();
        $this->sendKeys($lastName);
        $this->findById('search_dob')->click();
        $this->sendKeys($dob);

        //Click search
        $this->findBySelector('form[name=search] .btn-primary')->click();

        //Check if search result contains lastname
        $this->assertContains($lastName, $this->findByClass('table')->getText());
    }

    public function createPhysicalMeasurements()
    {
        //Click participant
        $this->findByXPath('/participant/'.$this->pmiId.'')->click();

        //Click start physical measurements
        $this->findByXPath('/participant/'.$this->pmiId.'/measurements')->click();

        //Enter blood pressure values
        $this->setInput('form[blood-pressure-systolic][0]', '112');
        $this->setInput('form[blood-pressure-systolic][1]', '122');
        $this->setInput('form[blood-pressure-systolic][2]', '126');

        $this->setInput('form[blood-pressure-diastolic][0]', '84');
        $this->setInput('form[blood-pressure-diastolic][1]', '82');
        $this->setInput('form[blood-pressure-diastolic][2]', '80');

        $this->setInput('form[heart-rate][0]', '80');
        $this->setInput('form[heart-rate][1]', '82');
        $this->setInput('form[heart-rate][2]', '84');

        //Enter height and weight values
        $this->setInput('form[height]', '140');
        $this->setInput('form[weight]', '56');

        //Enter waist and hip circumference
        $this->setInput('form[waist-circumference][0]', '32');
        $this->setInput('form[waist-circumference][1]', '33');
        $this->setInput('form[hip-circumference][0]', '34');
        $this->setInput('form[hip-circumference][1]', '35');

        //Save PM
        $this->findByClass('btn-primary')->click();

        //Check if PM is saved
        $this->assertContains('Physical measurements saved', $this->findById('flash-notices')->getText());

        //Finalize PM
        $this->findBySelector('.btn-success.pull-right')->click();
        $this->webDriver->switchTo()->alert()->accept();      
        $this->finalizedDate = strtotime($this->findBySelector('.dl-horizontal dd:last-child')->getText());
    }

    public function checkFinalizedPM()
    {
        //Go to Workqueue page
        $this->webDriver->get($this->baseUrl.'/workqueue?test='.time());

        //Get participant PM created date
        $elements = $this->webDriver->findElements(WebDriverBy::cssSelector('tbody tr'));
        for ($i = 1; $i <= count($elements); $i++) { 
            if ($this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(4)')->getText() == $this->pmiId) {
                $date = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(30)')->getText();
                break;
            }
        }

        //Check if PM finalized date exists
        $this->assertContains(date('m/d/Y',$this->finalizedDate), $date);      
    }
}
