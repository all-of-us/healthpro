<?php
namespace Pmi\Application;

class HpoApplication extends AbstractApplication
{
    public function setup()
    {
        parent::setup();

        $this['pmi.drc.participantsearch'] = new \Pmi\Drc\ParticipantSearch();

        return $this;
    }
}
