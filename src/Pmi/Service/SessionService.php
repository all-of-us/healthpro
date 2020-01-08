<?php

namespace Pmi\Service;

use Pmi\Entities\Session;

class SessionService
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function deleteKeys()
    {
        $modified = new \DateTime("-{$this->app['sessionTimeout']} seconds");
        $limit = $this->app->getConfig('ds_clean_up_limit');
        $session = new Session();
        $results = $session->getBatch('modified', $modified, '<', $limit);
        $session->deleteBatch($results);
        return true;
    }
}
