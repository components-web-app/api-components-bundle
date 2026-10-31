<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Repository\User;

use Doctrine\ORM\NonUniqueResultException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Repository\User\FindUserByUsernameTrait;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

final class FindUserByUsernameTraitTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function usernames(): iterable
    {
        yield 'the username' => ['my_username', true];
        yield 'the username in capitals with surrounding space' => [' MY_USERNAME ', true];
        yield 'the email address the identifier also matches' => ['user@example.com', false];
    }

    #[DataProvider('usernames')]
    public function test_only_the_users_own_username_finds_them(string $username, bool $found): void
    {
        $user = new User('my_username', 'user@example.com');
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('loadUserByIdentifier')->willReturn($user);

        self::assertSame($found ? $user : null, $this->finder()->find($repository, $username));
    }

    public function test_a_username_matching_several_users_finds_none(): void
    {
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('loadUserByIdentifier')->willThrowException(new NonUniqueResultException());

        self::assertNull($this->finder()->find($repository, 'my_username'));
    }

    private function finder(): object
    {
        return new class {
            use FindUserByUsernameTrait;

            public function find(UserRepositoryInterface $repository, string $username): ?AbstractUser
            {
                return $this->findUserByUsernameIn($repository, $username);
            }
        };
    }
}
