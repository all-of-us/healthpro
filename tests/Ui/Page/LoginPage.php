<?php
namespace Tests\Ui\Page;

use Facebook\WebDriver\WebDriverBy;

class LoginPage extends BasePage
{
    public function enterEmail($email)
    {
    	$this->findByName('email')->clear();
        $this->setInput('email', $email);

    }

    public function loginUser()
    {
        $this->findById('submit-login')->click();
    }
}
