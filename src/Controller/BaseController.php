<?php

namespace App\Controller;

use App\Entity\User as UserEntity;
use App\Security\User as SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BaseController extends AbstractController
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    protected function isReadOnly(): bool
    {
        $route = $this->container->get('request_stack')->getCurrentRequest()->get('_route');
        return strpos($route, 'read_') === 0;
    }

    protected function getUserEntity(): ?UserEntity
    {
        return $this->em->getRepository(UserEntity::class)->find($this->getSecurityUser()->getId());
    }

    protected function getSecurityUser(): SecurityUser
    {
        $user = $this->getUser();
        if ($user instanceof SecurityUser) {
            return $user;
        }
        throw new \Exception('Invalid user type');
    }

    protected function getParamDate($params, $key): ?\DateTime
    {
        return !empty($params[$key]) ? \DateTime::createFromFormat('!m/d/Y', $params[$key]) : null;
    }
}
