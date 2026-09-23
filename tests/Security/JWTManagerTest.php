<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Security;

use Doctrine\ORM\OptimisticLockException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\RefreshToken\Storage\RefreshTokenStorageInterface;
use Silverback\ApiComponentsBundle\Security\JWTManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class JWTManagerTest extends TestCase
{
    public function test_decode_returns_the_decorated_payload(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $decorated = $this->createMock(JWTTokenManagerInterface::class);
        $decorated->expects(self::once())->method('decode')->with($token)->willReturn(['username' => 'user']);

        $manager = new JWTManager($decorated, $this->createStub(EventDispatcherInterface::class), $this->createStub(UserProviderInterface::class), $this->createStub(RefreshTokenStorageInterface::class));

        self::assertSame(['username' => 'user'], $manager->decode($token));
    }

    public function test_decode_propagates_an_expired_token_without_refreshing_it(): void
    {
        $expired = new JWTDecodeFailureException(JWTDecodeFailureException::EXPIRED_TOKEN, 'Expired JWT Token', null, ['username' => 'user']);
        $decorated = $this->createMock(JWTTokenManagerInterface::class);
        $decorated->expects(self::once())->method('decode')->willThrowException($expired);
        $decorated->method('getUserIdClaim')->willReturn('username');

        $userProvider = $this->createMock(UserProviderInterface::class);
        $userProvider->expects(self::never())->method('loadUserByIdentifier');
        $storage = $this->createMock(RefreshTokenStorageInterface::class);
        $storage->expects(self::never())->method('findOneByUser');

        $manager = new JWTManager($decorated, $this->createStub(EventDispatcherInterface::class), $userProvider, $storage);

        $this->expectExceptionObject($expired);
        $manager->decode($this->createStub(TokenInterface::class));
    }

    public function test_create_still_issues_a_token_when_the_refresh_token_was_modified_concurrently(): void
    {
        $user = new InMemoryUser('user', null);
        $decorated = $this->createMock(JWTTokenManagerInterface::class);
        $decorated->expects(self::once())->method('create')->with($user)->willReturn('access-token');
        $storage = $this->createStub(RefreshTokenStorageInterface::class);
        $storage->method('createAndExpireAll')->willThrowException(new OptimisticLockException('modified', null));

        $manager = new JWTManager($decorated, $this->createStub(EventDispatcherInterface::class), $this->createStub(UserProviderInterface::class), $storage);

        self::assertSame('access-token', $manager->create($user));
    }
}
