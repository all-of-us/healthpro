<?php
namespace Tests\Ui;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\WebDriverExpectedCondition;

class HealthProTest extends AbstractPmiUiTestCase
{
    private $firstName;
    private $lastName;
    private $dob;
    private $participantId;
    private $pmFinalizedDate;

    public function testHealthPro()
    {
        $data = $this->getData();

        $this->setData($data);

        $this->login();

        $this->participantLookup();

        $this->createPhysicalMeasurements();

        $this->participantLookupById();

        $this->createBiobankOrder();

        $this->verifyParticipantSummary();

        $this->verifyDashboard();
    }

    public function getData()
    {
        $data = file_get_contents(__DIR__.'/PtscInput.json');
        return json_decode($data);
    }

    public function setData($data)
    {
        $this->firstName = $data->firstName;
        $this->lastName = $data->lastName;
        $this->dob = $data->dob;
        $this->participantId = $data->participantId;
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

        //Wait untill modal window completly disappears
        $driver = $this->webDriver;
        $this->webDriver->wait(5, 500)->until(
            function () use ($driver) {
                $elements = $driver->findElements(WebDriverBy::cssSelector('.modal-open'));
                return count($elements) == 0;
            }
        );

        //Select destination if exists
        if ($this->webDriver->getTitle() == 'Choose Destination - HealthPro') {
            //Click Healthpro destination
            $this->findByXPath('/')->click();            
        }

        //Select site if exists
        if ($this->webDriver->getTitle() == 'Choose Site - HealthPro') {
            $this->findByClass('site-submit')->submit();           
        }
    }

    public function participantLookup()
    {
        //Go to ParticipantsLookup page
        $this->findByXPath('/participants')->click();

        //Enter lastname and dob
        $this->findById('search_lastName')->click();
        $this->sendKeys($this->lastName);
        $this->findById('search_dob')->click();
        $this->sendKeys($this->dob);

        //Click search
        $this->findBySelector('form[name=search] .btn-primary')->click();

        //Check if search result contains lastname
        $this->assertContains($this->lastName, $this->findByClass('table')->getText());
    }

    public function createPhysicalMeasurements()
    {
        //Click participant
        $this->findByXPath('/participant/'.$this->participantId.'')->click();

        //Click start physical measurements
        $this->findByXPath('/participant/'.$this->participantId.'/measurements')->click();

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

        //Check if PM is finalized
        $this->assertContains('Finalized', $this->findByClass('alert-success')->getText());

        $this->pmFinalizedDate = strtotime($this->findBySelector('.dl-horizontal dd:last-child')->getText());
    }

    public function participantLookupById()
    {
        //Go to ParticipantsLookup page
        $this->findByXPath('/participants')->click();
        $this->findById('id_participantId')->click();
        $this->sendKeys($this->participantId);

        //Click go
        $this->findBySelector('form[name=id] .btn-primary')->click();  
    }

    public function createBiobankOrder()
    {
        //Click new order
        $this->findByXPath('/participant/'.$this->participantId.'/order/check')->click();

        //Safety Check
        $this->webDriver->findElements(WebDriverBy::name("donate"))[1]->click();
        $this->webDriver->findElements(WebDriverBy::name("transfusion"))[1]->click();
        $this->findByXPath('/participant/'.$this->participantId.'/order/create')->click();

        //Create HPO biobank order
        $this->findByName('standard')->click();

        //Print - check if the print labels exist
        $this->assertCount(1, $this->webDriver->findElements(WebDriverBy::className("pdf-requisition")));
        $this->assertCount(1, $this->webDriver->findElements(WebDriverBy::className("pdf-labels")));

        //Collect
        $this->findBySelector('ul li[role=presentation]:nth-child(4)')->click();
        $this->findByName('form[collected_ts]')->click();
        $this->findBySelector('.bootstrap-datetimepicker-widget a[data-action=close]')->click();
        $this->findById('checkall')->click();
        $this->findBySelector('.btn-primary[type=submit]')->click();

        //Check order collection
        $this->assertContains('Order collection', $this->findById('flash-notices')->getText());

        //Process
        $this->findBySelector('ul li[role=presentation]:nth-child(5)')->click();
        $this->findById('form_processed_samples_0')->click();
        $this->findByName('form[processed_samples_ts][1SST8]')->click();
        $this->findBySelector('.bootstrap-datetimepicker-widget a[data-action=close]')->click();

        $this->findById('form_processed_samples_1')->click();
        $this->findByName('form[processed_samples_ts][1PST8]')->click();
        $this->findBySelector('.bootstrap-datetimepicker-widget a[data-action=close]')->click();
        $this->findBySelector('.btn-primary[type=submit]')->click();

        //Check order processing
        $this->assertContains('Order processing', $this->findById('flash-notices')->getText());

        //Finalize
        $this->findBySelector('ul li[role=presentation]:nth-child(6)')->click();
        $this->findByName('form[finalized_ts]')->click();
        $this->findBySelector('.bootstrap-datetimepicker-widget a[data-action=close]')->click();
        $this->findById('checkall')->click();
        $this->findBySelector('.btn-primary[type=submit]')->click();
        $this->webDriver->switchTo()->alert()->accept();

        //Check order finalization
        $this->assertContains('Order finalization', $this->findById('flash-notices')->getText());
    }

    public function verifyParticipantSummary()
    {
        //Go to Workqueue page
        $this->findByXPath('/workqueue/')->click();

        //Show all columns
        $this->findBySelector('.dt-buttons a:nth-child(8)')->click();

        //Display 100 rows
        $select = new WebDriverSelect($this->findByName('workqueue_length'));
        $select->selectByValue('100');

        //Sort physical measurements by desending order
        $this->findBySelector('thead tr:nth-child(2) th:nth-child(21)')->click();
        $this->findBySelector('thead tr:nth-child(2) th:nth-child(21)')->click();

        //Get participant PM finalized date
        $elements = $this->webDriver->findElements(WebDriverBy::cssSelector('tbody tr'));
        for ($i = 1; $i <= count($elements); $i++) {
            if ($this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(4)')->getText() == $this->participantId) {
                $date = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(30)')->getText();
                break;
            }
        }

        //Check if PM finalized date exists
        $this->assertEquals(date('m/d/Y',$this->pmFinalizedDate), $date);
    }

    public function verifyDashboard()
    {
        $dashboard = $this->webDriver->findElements(WebDriverBy::xpath("//a[@href='/dashboard/']"));
        if (count($dashboard)) {
            $this->findByXPath('/dashboard/')->click();
            
            //Check if page is loaded
            $driver = $this->webDriver;
            $this->webDriver->wait(60, 500)->until(
                function () use ($driver) {
                    $elements = $driver->findElements(WebDriverBy::cssSelector('.modal-open'));
                    return count($elements) == 0;
                }
            );
        }
    }
}
