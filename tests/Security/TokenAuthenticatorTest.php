<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Security;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Entity\User\TokenUser;
use Silverback\ApiComponentsBundle\Exception\ApiPlatformAuthenticationException;
use Silverback\ApiComponentsBundle\Exception\TokenAuthenticationException;
use Silverback\ApiComponentsBundle\Security\TokenAuthenticator;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class TokenAuthenticatorTest extends TestCase
{
    private TokenAuthenticator $tokenAuthenticator;
    /**
     * @var MockObject|Security
     */
    private MockObject $securityMock;
    /**
     * @var MockObject|SerializeFormatResolverInterface
     */
    private MockObject $serializeFormatResolver;

    protected function setUp(): void
    {
        $this->securityMock = $this->createMock(Security::class);
        $this->serializeFormatResolver = $this->createMock(SerializeFormatResolverInterface::class);
        $this->tokenAuthenticator = new TokenAuthenticator($this->securityMock, $this->serializeFormatResolver, ['valid_token']);
    }

    public function test_does_not_support_if_already_logged_in_user(): void
    {
        $this->securityMock
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(new class() extends AbstractUser {
            });

        $request = new Request();
        $this->assertFalse($this->tokenAuthenticator->supports($request));
    }

    public function test_supported_request(): void
    {
        $this->securityMock
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $request = new Request();
        $request->headers->add(['X-AUTH-TOKEN' => 'any token']);
        $this->assertTrue($this->tokenAuthenticator->supports($request));
    }

    public function test_get_credentials(): void
    {
        $request = new Request();
        $request->headers->add(['X-AUTH-TOKEN' => 'abc']);
        $expected = [
            'token' => 'abc',
        ];
        $this->assertEquals($expected, $this->tokenAuthenticator->getCredentials($request));
    }

    public function test_do_not_get_user_null_token(): void
    {
        $credentials = [
            'token' => null,
        ];
        $this->expectException(TokenAuthenticationException::class);
        $this->tokenAuthenticator->getUser($credentials);
    }

    public function test_do_not_get_user_invalid_token(): void
    {
        $credentials = [
            'token' => 'abc',
        ];
        $this->expectException(TokenAuthenticationException::class);
        $this->tokenAuthenticator->getUser($credentials);
    }

    public function test_get_user_valid_token(): void
    {
        $credentials = [
            'token' => 'valid_token',
        ];
        $this->assertInstanceOf(TokenUser::class, $this->tokenAuthenticator->getUser($credentials));
    }

    public function test_check_credentials_is_true(): void
    {
        $this->assertTrue($this->tokenAuthenticator->checkCredentials([], $this->createMock(UserInterface::class)));
    }

    public function test_on_authentication_success(): void
    {
        $request = new Request();
        $tokenInterfaceMock = $this->createMock(TokenInterface::class);
        $providerKey = null;
        $this->assertNull($this->tokenAuthenticator->onAuthenticationSuccess($request, $tokenInterfaceMock, $providerKey));
    }

    public function test_on_authentication_failure(): void
    {
        $request = new Request();
        $authenticationException = new AuthenticationException();

        $this->serializeFormatResolver
            ->expects($this->once())
            ->method('getFormatFromRequest')
            ->with($request)
            ->willReturn('jsonld');

        $this->expectException(ApiPlatformAuthenticationException::class);
        $this->expectExceptionMessage(strtr($authenticationException->getMessageKey(), $authenticationException->getMessageData()));

        $this->tokenAuthenticator->onAuthenticationFailure($request, $authenticationException);
    }

    public function test_start_output(): void
    {
        $request = new Request();

        $this->serializeFormatResolver
            ->expects($this->once())
            ->method('getFormatFromRequest')
            ->with($request)
            ->willReturn('jsonld');

        $this->expectException(ApiPlatformAuthenticationException::class);
        $this->expectExceptionMessage('Token Authentication Required.');

        $this->tokenAuthenticator->start($request);
    }

    public function test_start_output_with_exception(): void
    {
        $request = new Request();

        $authenticationException = new AuthenticationException();

        $this->serializeFormatResolver
            ->expects($this->once())
            ->method('getFormatFromRequest')
            ->with($request)
            ->willReturn('jsonld');

        $this->expectException(ApiPlatformAuthenticationException::class);
        $this->expectExceptionMessage($authenticationException->getMessage());

        $this->tokenAuthenticator->start($request, $authenticationException);
    }

    public function test_supports_remember_me(): void
    {
        $this->assertFalse($this->tokenAuthenticator->supportsRememberMe());
    }
}
