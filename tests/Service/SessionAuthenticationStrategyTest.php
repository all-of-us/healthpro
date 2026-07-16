<?php

namespace App\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

class SessionAuthenticationStrategyTest extends KernelTestCase
{
    private const MINIMUM_SESSION_ENTROPY_BITS = 128;

    public function testSessionIdIsRegeneratedOnAuthentication()
    {
        self::bootKernel();

        $session = static::getContainer()->get('session.factory')->createSession();
        $session->start();
        $session->set('testKey', 'testValue');

        $initialSessionId = $session->getId();

        $request = new Request();
        $request->setSession($session);

        /** @var RequestStack $requestStack */
        $requestStack = static::getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        /** @var SessionAuthenticationStrategyInterface $strategy */
        $strategy = static::getContainer()->get('security.authentication.session_strategy.main');

        /** @var TokenInterface&MockObject $token */
        $token = $this->createMock(TokenInterface::class);

        $strategy->onAuthentication($request, $token);

        $this->assertNotSame($initialSessionId, $session->getId(), 'Session ID should change after authentication.');
        $this->assertSame('testValue', $session->get('testKey'), 'Session data should be preserved with migrate strategy.');
    }

    public function testSessionConfigurationMeetsMinimumEntropyRequirement()
    {
        $sidLength = (int) ini_get('session.sid_length');
        $sidBitsPerCharacter = (int) ini_get('session.sid_bits_per_character');
        $totalEntropyBits = $sidLength * $sidBitsPerCharacter;

        $this->assertGreaterThanOrEqual(
            self::MINIMUM_SESSION_ENTROPY_BITS,
            $totalEntropyBits,
            sprintf(
                'Configured session entropy is too low: %d bits (sid_length=%d, sid_bits_per_character=%d).',
                $totalEntropyBits,
                $sidLength,
                $sidBitsPerCharacter
            )
        );
    }

    public function testGeneratedSessionIdMeetsMinimumLengthAndAlphabet()
    {
        self::bootKernel();

        $session = static::getContainer()->get('session.factory')->createSession();
        $session->start();
        $sessionId = $session->getId();

        $sidBitsPerCharacter = (int) ini_get('session.sid_bits_per_character');
        $minimumCharacters = (int) ceil(self::MINIMUM_SESSION_ENTROPY_BITS / $sidBitsPerCharacter);

        $this->assertGreaterThanOrEqual(
            $minimumCharacters,
            strlen($sessionId),
            sprintf('Session ID length is too short: %d chars (minimum expected: %d).', strlen($sessionId), $minimumCharacters)
        );

        $allowedPatterns = [
            4 => '/^[0-9a-f]+$/',
            5 => '/^[0-9a-v]+$/',
            6 => '/^[0-9a-zA-Z,-]+$/',
        ];

        $this->assertArrayHasKey(
            $sidBitsPerCharacter,
            $allowedPatterns,
            sprintf('Unexpected session.sid_bits_per_character value: %d.', $sidBitsPerCharacter)
        );

        $this->assertMatchesRegularExpression(
            $allowedPatterns[$sidBitsPerCharacter],
            $sessionId,
            sprintf('Session ID contains invalid characters for sid_bits_per_character=%d.', $sidBitsPerCharacter)
        );
    }
}
