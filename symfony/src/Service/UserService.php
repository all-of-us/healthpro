<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Pmi\Service\MockUserService;
use App\Entity\User;
use Symfony\Component\Security\Core\Security;

class UserService
{
    private $em;
    private $params;
    private $env;
    private $session;
    private $security;

    public function __construct(
        EntityManagerInterface $em,
        ContainerBagInterface $params,
        EnvironmentService $env,
        SessionInterface $session,
        Security $security
    ) {
        $this->em = $em;
        $this->params = $params;
        $this->env = $env;
        $this->session = $session;
        $this->security = $security;
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
        return $this->env->isLocal() && $this->params->has('local_mock_auth') && $this->params->get('local_mock_auth');
    }

    public function getUserInfo($googleUser)
    {
        if ($this->env->values['isUnitTest']) {
            return [
                'id' => 1,
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getUserId(),
                'timezone' => $googleUser->getTimzezone()
            ];
        }

        $attempts = 0;
        $maxAttempts = 3;
        do {
            try {
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => $googleUser->getEmail()]);
                break;
            } catch (\Exception $e) {
                if ($attempts == 2) {
                    sleep(1);
                }
                $attempts++;
            }
        } while ($attempts < $maxAttempts);
        if (!$user) {
            $user = new User;
            $user->setEmail($googleUser->getEmail());
            $user->setGoogleId($googleUser->getUserId());
            $this->em->persist($user);
            $this->em->flush();
        }

        if (empty($user)) {
            throw new AuthenticationException('Failed to retrieve user information');
        }
        // Return user info in array format
        $userInfo = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'google_id' => $user->getGoogleId(),
            'timezone' => $user->getTimezone(),
        ];
        return $userInfo;
    }

    public function getUser()
    {
        $token = $this->security->getToken();
        if ($token && is_object($token->getUser())) {
            return $token->getUser();
        }
        return null;
    }

    public function updateLastLogin(): void
    {
        $userInfo = $this->getUser();
        if ($userInfo) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $userInfo->getEmail()]);
            if ($user) {
                $user->setLastLogin(new \DateTime());
                $this->em->persist($user);
                $this->em->flush();
            }
        }
    }
}
