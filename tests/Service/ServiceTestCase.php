<?php

namespace App\Tests\Service;

use App\Service\MockGoogleGroupsService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use App\Tests\GoogleGroup;

class ServiceTestCase extends KernelTestCase
{
    public const GROUP_DOMAIN = 'healthpro-test.pmi-ops.org';
    protected $session;

    public function setUp(): void
    {
        self::bootKernel();
        $this->session = self::$container->get(SessionInterface::class);
    }

    protected function login(string $email, array $groups = ['hpo-site-test'])
    {
        $userService = self::$container->get(UserService::class);
        $tokenStorage = self::$container->get(TokenStorageInterface::class);
        $userProvider = self::$container->get(UserProviderInterface::class);

        $userService->setMockUser($email);
        if (count($groups) > 0) {
            $googleGroups = [];
            foreach ($groups as $groupId) {
                $googleGroups[] = new GoogleGroup(
                    "{$groupId}@" . self::GROUP_DOMAIN,
                    "{$groupId} name",
                    "{$groupId} description"
                );
            }
            MockGoogleGroupsService::setGroups($email, $googleGroups);
        }

        $user = $userProvider->loadUserByUsername($email);
        $token = new PreAuthenticatedToken($user, null, 'main', $user->getRoles());
        $tokenStorage->setToken($token);

        $this->session->set('_security_main', serialize($token));
        $this->session->set('isLoginReturn', true);
        $this->session->save();
    }
}
