<?php

namespace App\Service;

use App\Drc\GoogleUser;
use App\Drc\SalesforceUser;
use App\Entity\User;
use App\Helper\MockUserHelper;
use App\Security\MockUser;
use App\Security\User as SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;

class UserService
{
    private EntityManagerInterface $em;
    private ContainerBagInterface $params;
    private EnvironmentService $env;
    private RequestStack $requestStack;
    private Security $security;
    private AuthorizationCheckerInterface $authorizationChecker;

    public function __construct(
        EntityManagerInterface $em,
        ContainerBagInterface $params,
        EnvironmentService $env,
        RequestStack $requestStack,
        Security $security,
        AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->em = $em;
        $this->params = $params;
        $this->env = $env;
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function getGoogleUser(): GoogleUser|MockUser|null
    {
        if ($this->canMockLogin()) {
            return $this->requestStack->getSession()->get('mockUser');
        }
        return $this->requestStack->getSession()->get('googleUser');
    }

    public function getSalesforceUser(): ?SalesforceUser
    {
        return $this->requestStack->getSession()->get('salesforceUser');
    }

    public function canMockLogin(): bool
    {
        return $this->env->isLocal() && $this->params->has('local_mock_auth') && $this->params->get('local_mock_auth');
    }

    /** @return array<string, int|string|null> */
    public function getUserInfo(GoogleUser|SalesforceUser|MockUser $oauthUser): array
    {
        $attempts = 0;
        $maxAttempts = 3;
        do {
            try {
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => $oauthUser->getEmail()]);
                break;
            } catch (\Exception $e) {
                if ($attempts == 2) {
                    sleep(1);
                }
                $attempts++;
            }
        } while ($attempts < $maxAttempts);
        if (empty($user)) {
            $user = new User();
            $user->setEmail($oauthUser->getEmail());
            $user->setGoogleId($oauthUser->getUserId());
            if ($oauthUser->getTimezone()) {
                $user->setTimezone($oauthUser->getTimezone());
            }
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

    public function getUser(): ?SecurityUser
    {
        $token = $this->security->getToken();
        $user = $token?->getUser();
        if ($user instanceof SecurityUser) {
            return $user;
        }
        return null;
    }

    /**
     * @param array<int, string> $roles
     * @return array<int, string>
     */
    public function getRoles(array $roles, mixed $site, mixed $awardee): array
    {
        if (!empty($site)) {
            User::removeUserRoles(['ROLE_AWARDEE', 'ROLE_AWARDEE_SCRIPPS'], $roles);
            if ($this->requestStack->getSession()->get('program') === User::PROGRAM_NPH) {
                User::removeUserRoles(['ROLE_USER'], $roles);
            } else {
                User::removeUserRoles(['ROLE_NPH_USER'], $roles);
            }
        }
        if (!empty($awardee)) {
            User::removeUserRoles(['ROLE_USER', 'ROLE_NPH_USER'], $roles);
            if (isset($awardee->id) && $awardee->id !== User::AWARDEE_SCRIPPS) {
                User::removeUserRoles(['ROLE_AWARDEE_SCRIPPS'], $roles);
            }
        }
        return $roles;
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
        $this->requestStack->getSession()->set('isLoginReturn', true);
    }

    public function setMockUser(string $email): void
    {
        MockUserHelper::switchCurrentUser($email);
        $this->requestStack->getSession()->set('mockUser', MockUserHelper::getCurrentUser());
    }

    /** Is the user's session expired? */
    public function isLoginExpired(): bool
    {
        $time = time();
        // custom "last used" session time updated on keepAliveAction
        $idle = $time - $this->requestStack->getSession()->get('pmiLastUsed', $time);
        $remaining = $this->env->values['sessionTimeOut'] - $idle;
        return $this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY') && $remaining <= 0;
    }

    public function getUserEntity(): ?User
    {
        if ($this->getUser()) {
            return $this->em->getRepository(User::class)->find($this->getUser()->getId());
        }
        return null;
    }

    public function getUserEntityFromEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }
}
