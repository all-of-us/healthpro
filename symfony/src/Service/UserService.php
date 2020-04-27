<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Pmi\Service\MockUserService;
use App\Entity\User;

class UserService
{
    private $em;
    private $params;
    private $env;
    private $session;

    public function __construct(EntityManagerInterface $em, ContainerBagInterface $params, EnvironmentService $env, SessionInterface $session)
    {
        $this->em = $em;
        $this->params = $params;
        $this->env = $env;
        $this->session = $session;
    }

    public function getGoogleUser()
    {
        if ($this->canMockLogin()) {
            if ($this->env->values['isUnitTest']) {
                return MockUserService::getCurrentUser();
            } else {
                return $this->session->get('mockUser');
            }
        } else {
            return $this->session->get('googleUser');
        }
    }

    public function canMockLogin()
    {
        return $this->env->isLocal() && $this->params->get('local_mock_auth');
    }

    public function getUserInfo($googleUser)
    {
        if ($this->env->values['isUnitTest']) {
            return [
                'id' => 1,
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getUserId(),
            ];
        }
        $userInfo = $this->em->getRepository(User::class)->findOneBy(['email' => $googleUser->getEmail()]);
        if (empty($userInfo)) {
            throw new AuthenticationException('Failed to retrieve user information');
        }
        return $userInfo;
    }
}
