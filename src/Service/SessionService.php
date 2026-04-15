<?php

namespace App\Service;

use App\Datastore\Entities\Session;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SessionService
{
    private ParameterBagInterface $params;
    private EnvironmentService $env;

    public function __construct(ParameterBagInterface $params, EnvironmentService $env)
    {
        $this->params = $params;
        $this->env = $env;
    }

    public function deleteKeys(): bool
    {
        $sessionTimeout = $this->env->values['sessionTimeOut'];
        $modified = new \DateTime("-{$sessionTimeout} seconds");
        $limit = (int) $this->params->get('ds_clean_up_limit');
        $session = new Session();
        $results = $session->getBatch('modified', $modified, '<', $limit);
        $session->deleteBatch($results);
        return true;
    }
}
