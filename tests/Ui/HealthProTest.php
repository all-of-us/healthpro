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
    private $participantEmail;
    private $hpoAffiliation;
    private $generalConsent;
    private $ppiCompletedSurveys;
    private $participantId;
    private $pmFinalizedDate;

    public function testHealthPro()
    {
        $this->login();
        $participants = $this->getData();
        foreach ($participants as $participant) {
            $this->setData($participant);
            $this->participantLookup();
            if ($this->checkParticipantEligibility()) {
                $this->createPhysicalMeasurements();
                $this->participantLookupById();
                $this->createBiobankOrder();
                $this->verifyParticipantSummary();
            }
        }
        $this->verifyDashboard();
    }

    public function getData()
    {
        if (getenv('data') == 'json') {
            $data = $this->getParticipantDataFromPtscJson();
        } else {
            $data = $this->getParticipantDataFromWorkQueue();
        }

        return $data['participantsInfo'];
    }

    public function setData($data)
    {
        $this->firstName = $data['firstName'];
        $this->lastName = $data['lastName'];
        $this->dob = $data['dob'];
        $this->participantEmail = $data['participantEmail'];
        $this->hpoAffiliation = $data['hpoAffiliation'];
        $this->generalConsent = $data['generalConsent'];
        $this->ppiCompletedSurveys = $data['ppiCompletedSurveys'];
    }

    public function getParticipantDataFromPtscJson()
    {
        $data = file_get_contents($this->getConfig('ptsc_json_path'));
        return json_decode($data, true);
    }

    public function getParticipantDataFromWorkQueue()
    {
        //Go to Workqueue page
        $this->findByXPath('/workqueue/')->click();

        $this->findBySelector('.dt-buttons a:nth-child(8)')->click();
        $select = new WebDriverSelect($this->findByName('workqueue_length'));
        $select->selectByValue('100');

        //Select participant who's status is not withdrawn, completed basic survery and doesn't have a PM
        $elements = $this->webDriver->findElements(WebDriverBy::cssSelector('tbody tr'));
        $data = [];
        for ($i = 1; $i <= count($elements); $i++) { 
            $withdrawn = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(9)')->getText();
            $basicsDate = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(17)')->getText();
            $pmDate = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(30)')->getAttribute('data-order');
            $dob = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(3)')->getText();
            if (empty($withdrawn) && !empty($basicsDate) && $pmDate == '0-' && !empty($dob)) {
                $data['firstName'] = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(2)')->getText();
                $data['lastName'] = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(1)')->getText();
                $data['participantEmail'] = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(12)')->getText();
                $data['dob'] = $dob;
                break;
            }
        }

        //Throw exception if the desired participant not found.
        if (empty($data)) {
            throw new \Exception("Participant not found");            
        } else {
            $participants = [];
            $participants['participantsInfo'][] = $data;
            return $participants;
        }
    }

    public function login()
    {
        $this->webDriver->get($this->baseUrl);

        $email = $this->getConfig('user_name');
        $password = $this->getConfig('password');

        //Sign in with google account if exists
        if ($this->webDriver->getTitle() == 'Sign in - Google Accounts') {
            $this->setInput('identifier', $email);
            $this->findById('identifierNext')->click();
            $this->waitForElementVisible(WebDriverBy::name('password'));
            $this->waitForElementClickable(WebDriverBy::name('password'));
            $this->setInput('password', $password);
            $this->waitForElementClickable(WebDriverBy::id('passwordNext'));
            $this->findById('passwordNext')->click();
        } else {
            $this->findBySelector('.container a')->click();
            $this->findByName('email')->clear();
            $this->setInput('email', $email);
            $this->findById('submit-login')->click();            
        }

        $this->waitForClassVisible('pmi-confirm-ok');
        $element = $this->findByClass('pmi-confirm-ok');

        //Make mouse movement to trigger keepalive ajax call
        $this->webDriver->getMouse()->mouseMove($element->getCoordinates());

        //Click agree
        $element->click();

        //Wait untill modal window completly disappears
        $driver = $this->webDriver;
        $this->webDriver->wait(15, 500)->until(
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

        //Select timezone if exists
        if ($this->webDriver->findElements(WebDriverBy::id('form_timezone'))) {
            $select = $this->findByName('form[timezone]');
            $options = $select->findElements(WebDriverBy::tagName('option'));
            foreach ($options as $option) {
                $timeZones[] = $option->getAttribute('value');
            }
            $timeZone = date_default_timezone_get();

            //Select a default timezone if not present in the options
            if (!in_array($timeZone, array_slice($timeZones, 1))) {
                $timeZone = 'America/Chicago';
            }
            $select = new WebDriverSelect($this->findByName('form[timezone]'));
            $select->selectByValue($timeZone);
            $this->findByClass('btn-primary')->click();        
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

    public function checkParticipantEligibility()
    {
        //Click participant
        $this->findBySelector('tbody tr:nth-child(1) td:nth-child(1) a')->click();

        if (isset($this->ppiCompletedSurveys) && $this->ppiCompletedSurveys == 0) {
            $this->assertContains('The Basics survey not complete', $this->findBySelector('.panel-danger .panel-heading')->getText());
            return false;
        }

        if (isset($this->generalConsent) && $this->generalConsent == false) {
            $this->assertContains('Consent not complete', $this->findBySelector('.panel-danger .panel-heading')->getText());
            return false;
        }

        return true;
    }

    public function createPhysicalMeasurements()
    {
        //Get participantId
        $this->participantId = $this->findBySelector('.dl-horizontal dd:nth-child(4)')->getText();

        //Click start physical measurements
        $this->findByXPath('/participant/'.$this->participantId.'/measurements')->click();

        $formData = file_get_contents(__DIR__.'/FormInputs.json');
        $formData = json_decode($formData, true);
        $physicalMeasurements = $formData['physicalMeasurements'][0];

        //Enter blood pressure values
        $this->setInput('form[blood-pressure-systolic][0]', $physicalMeasurements['bloodPressureSystolic']);
        $this->setInput('form[blood-pressure-systolic][1]', $physicalMeasurements['bloodPressureSystolic1']);
        $this->setInput('form[blood-pressure-systolic][2]', $physicalMeasurements['bloodPressureSystolic2']);

        $this->setInput('form[blood-pressure-diastolic][0]', $physicalMeasurements['bloodPressureDiastolic']);
        $this->setInput('form[blood-pressure-diastolic][1]', $physicalMeasurements['bloodPressureDiastolic1']);
        $this->setInput('form[blood-pressure-diastolic][2]', $physicalMeasurements['bloodPressureDiastolic2']);

        $this->setInput('form[heart-rate][0]', $physicalMeasurements['heartRate']);
        $this->setInput('form[heart-rate][1]', $physicalMeasurements['heartRate1']);
        $this->setInput('form[heart-rate][2]', $physicalMeasurements['heartRate2']);

        //Enter height and weight values
        $this->setInput('form[height]', $physicalMeasurements['height']);
        $this->setInput('form[weight]', $physicalMeasurements['weight']);

        //Enter waist and hip circumference
        $this->setInput('form[waist-circumference][0]', $physicalMeasurements['waistCircumference']);
        $this->setInput('form[waist-circumference][1]', $physicalMeasurements['waistCircumference1']);
        $this->setInput('form[hip-circumference][0]', $physicalMeasurements['hipCircumference']);
        $this->setInput('form[hip-circumference][1]', $physicalMeasurements['hipCircumference1']);

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
        $this->waitForClassVisible('pdf-requisition');
        $this->waitForClassVisible('pdf-labels');
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
            if ($this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(12)')->getText() == $this->participantEmail) {
                $date = $this->findBySelector('tbody tr:nth-child('.$i.') td:nth-child(30)')->getText();
                break;
            }
        }

        //Check if PM finalized date exists
        $this->assertEquals(date('m/d/Y',$this->pmFinalizedDate), $date);

        if (isset($this->hpoAffiliation)) {
            //Check HPO affiliation
            $this->assertContains($this->hpoAffiliation, $this->findBySelector('small h4')->getText());
        }
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
