<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProvider\StateProvider;

use ApiPlatform\Metadata\Get;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\UserStateProvider;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;

class UserStateProviderTest extends TestCase
{
    public function test_the_stored_user_is_loaded_by_the_username_of_the_token_user(): void
    {
        $tokenUser = (new User())->setUsername('daniel');
        $storedUser = (new User())->setUsername('daniel');
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())->method('loadUserByIdentifier')->with('daniel')->willReturn($storedUser);

        self::assertSame($storedUser, (new UserStateProvider($repository, $this->security($tokenUser)))->provide(new Get(name: '_api_me')));
    }

    public function test_no_token_user_is_denied(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::never())->method('loadUserByIdentifier');

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access denied.');
        (new UserStateProvider($repository, $this->security(null)))->provide(new Get(name: '_api_me'));
    }

    public function test_a_token_user_that_is_not_a_bundle_user_is_denied(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::never())->method('loadUserByIdentifier');

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access denied. User not supported.');
        (new UserStateProvider($repository, $this->security(new InMemoryUser('daniel', null))))->provide(new Get(name: '_api_me'));
    }

    public function test_a_token_user_with_no_username_is_not_found(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::never())->method('loadUserByIdentifier');

        self::assertNull((new UserStateProvider($repository, $this->security(new User())))->provide(new Get(name: '_api_me')));
    }

    public function test_a_token_user_that_is_no_longer_stored_is_unauthorized(): void
    {
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('loadUserByIdentifier')->willReturn(null);

        $this->expectException(UnauthorizedHttpException::class);
        (new UserStateProvider($repository, $this->security((new User())->setUsername('gone'))))->provide(new Get(name: '_api_me'));
    }

    private function security(mixed $user): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return $security;
    }
}
