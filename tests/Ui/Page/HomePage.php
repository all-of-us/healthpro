<?php
namespace Tests\Ui\Page;

class HomePage extends BasePage
{
    protected $path = '/';

    public function clickTryAgain()
    {
        $this->findBySelector('.container a')->click();
    }

    public function clickAgree()
    {
    	$this->findBySelector('.pmi-confirm-ok')->click();
    }
}
