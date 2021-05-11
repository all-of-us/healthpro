<?php

namespace App\Service;

use Pmi\Entities\Session;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SessionService
{
    private $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function deleteKeys()
    {
        $sessionTimeout = 30 * 60;
        $modified = new \DateTime("-{$sessionTimeout} seconds");
        $limit = $this->params->get('ds_clean_up_limit');
        $session = new Session();
        $results = $session->getBatch('modified', $modified, '<', $limit);
        $session->deleteBatch($results);
        return true;
    }
}
