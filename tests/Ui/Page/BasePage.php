<?php
namespace Tests\Ui\Page;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * Basic page object.  See http://martinfowler.com/bliki/PageObject.html for an overview
 */
class BasePage
{
    protected $webDriver;
    protected $baseUrl = 'http://localhost:8080';
    protected $path = '/';

    /**
     * @param $driver The WebDriver to use
     * @param $path optional path to load
     */
    public function __construct($driver)
    {
        $this->webDriver = $driver;
    }

    /**
     * Make GET request to page path
     */
    public function get()
    {
        $url = $this->baseUrl . $this->path;
        $this->webDriver->get($url);
    }

    /**
     * Waits for the given element to become visible
     */
    public function waitForElementVisible($elt)
    {
        $this->webDriver->wait(5, 500)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated($elt)
        );
    }

    public function waitForIdVisible($id)
    {
        return $this->waitForElementVisible(WebDriverBy::id($id));
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

    public function sendKeys($text)
    {
        $this->webDriver->getKeyboard()->sendKeys($text);
    }

    private function getInvalidFields()
    {
        return array_merge(
            $this->webDriver->findElements(WebDriverBy::cssSelector('input:invalid')),
            $this->webDriver->findElements(WebDriverBy::cssSelector('select:invalid'))
        );
    }
    
    public function getErrorFields($form)
    {
        $fields = [];
        try {
            $groups = $form->findElements(WebDriverBy::cssSelector('.form-group.has-error'));
        } catch (\Exception $e) {
            return $fields;
        }
        foreach ($groups as $group) {
            try {
                $inputs = $group->findElements(WebDriverBy::cssSelector('input,select,textarea'));
                $fields = array_merge($fields, $inputs);
            } catch (\Exception $e) {
                continue;
            }
        }
        return $fields;
    }

    public function textExistsInPage($text)
    {
        $body = $this->webDriver->findElement(WebDriverBy::tagName("body"));
        return strpos($body->getText(),$text) > -1;
    }

    public function setInput($name, $value)
    {
        $input = $this->findByName($name);
        $input->click();
        $this->sendKeys($value);
    }

    public function selectOption($name, $value)
    {
        $select = new WebDriverSelect($this->findByName($name));
        $select->selectByValue($value);
    }

    /**
     * Checks whether the HTML5 invalid flag is set
     * for a given element, using the given attribute name/value pair.
     * @return true if the element identified by $attribute=$value
     * was marked as invalid by html5 form validation
     */
    public function isHtml5Invalid($attribute, $value)
    {
        $isInvalid = false;
        foreach($this->getInvalidFields() as $invalidField) {
            $attributeValue = $invalidField->getAttribute($attribute);
            if (strcmp($attributeValue, $value) == 0) {
                $isInvalid = true;
            }
        }
        return $isInvalid;
    }
}
