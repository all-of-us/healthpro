<?php
namespace Pmi\Entities;

use Pmi\Util;

class Participant
{
    public $id;
    public $firstName;
    public $lastName;
    public $dob;
    public $gender;
    public $zip;
    public $consentComplete;

    public function __construct($options = null)
    {
        if (is_object($options) || is_array($options)) {
            $this->setData($options);
        }
    }

    public function setData($options)
    {
        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function getShortId()
    {
        if (strlen($this->id) >= 36) {
            return strtoupper(Util::shortenUuid($this->id));
        } else {
            return $this->id;
        }
    }
}
