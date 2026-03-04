<?php

namespace App\Tests\Service;

use App\Service\MockGoogleGroupsService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use App\Tests\GoogleGroup;

abstract class ServiceTestCase extends KernelTestCase
{
    public const GROUP_DOMAIN = 'healthpro-test.pmi-ops.org';
    protected $session;
    protected $request;
    protected $requestStack;
    protected $program = 'hpo';

    public function setUp(): void
    {
        self::bootKernel();
        $this->session = static::getContainer()->get('session.factory')->createSession();
        $this->requestStack = static::getContainer()->get(RequestStack::class);
        $this->request = new Request();
        $this->request->setSession($this->session);
        $this->requestStack->push($this->request);
    }

    protected function login(string $email, array $groups = ['hpo-site-test'], string $timezone = null)
    {
        $userService = static::getContainer()->get(UserService::class);
        $tokenStorage = static::getContainer()->get(TokenStorageInterface::class);
        $userProvider = static::getContainer()->get(UserProviderInterface::class);

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
        if ($timezone) {
            $user->setTimezone('America/Chicago');
        }
        $token = new PreAuthenticatedToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken($token);

        $this->session->set('_security_main', serialize($token));
        $this->session->set('isLoginReturn', true);
        $this->session->set('program', $this->program);
        $this->session->save();
    }
}
