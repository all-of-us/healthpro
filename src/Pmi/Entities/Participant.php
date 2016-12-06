<?php
namespace Pmi\Entities;

use Pmi\Util;

class Participant
{
    public $id;
    public $biobankId;
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

    public function getMayolinkDob($type = null)
    {
        if ($type == 'kit') {
            return new \DateTime('1960-01-01');
        } else {
            $mlDob = new \DateTime();
            $mlDob->setDate(1960, $this->dob->format('m'), $this->dob->format('d'));
            return $mlDob;
        }
    }
}
