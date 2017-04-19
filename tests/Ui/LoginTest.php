<?php
namespace Tests\Ui;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Tests\Ui\Page;

class LoginTest extends AbstractPmiUiTestCase
{
    public function testLogin()
    {
        $email = 'test@example.com';

        $homePage = new Page\HomePage($this->webDriver);
        $homePage->get();
        $this->assertContains('Error - HealthPro', $this->webDriver->getTitle());
        $homePage->clickTryAgain();

        $loginPage = new Page\LoginPage($this->webDriver);
        $this->assertContains('Login', $this->webDriver->getTitle());
        $loginPage->enterEmail($email);
        $loginPage->loginUser();
        
        $homePage->waitForClassVisible('pmi-confirm-ok');
        $homePage->clickAgree();
        $this->assertContains('Choose Destination - HealthPro', $this->webDriver->getTitle());

    }
}
