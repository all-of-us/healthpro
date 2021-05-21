<?php

namespace App\Service;

use Pmi\Entities\Session;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SessionService
{
    private $params;
    private $env;

    public function __construct(ParameterBagInterface $params, EnvironmentService $env)
    {
        $this->params = $params;
        $this->env = $env;
    }

    public function deleteKeys()
    {
        $sessionTimeout = $this->env->getSessionTimeout();
        $modified = new \DateTime("-{$sessionTimeout} seconds");
        $limit = $this->params->get('ds_clean_up_limit');
        $session = new Session();
        $results = $session->getBatch('modified', $modified, '<', $limit);
        $session->deleteBatch($results);
        return true;
    }
}
