<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\User;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\EventListener\Api\UserEventListener;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\User\EmailAddressManager;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Tests\Command\InMemoryUserRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[AllowMockObjectsWithoutExpectations]
class EmailAddressManagerTest extends TestCase
{
    public function test_the_user_with_the_username_is_verified(): void
    {
        $user = $this->createUnverifiedUser('bob', 'bob@example.com');
        $other = $this->createUnverifiedUser('alice', 'alice@example.com');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $this->createManager([$other, $user], $entityManager)->verifyEmailAddress('Bob', 'token');

        self::assertTrue($user->isEmailAddressVerified());
        self::assertFalse($other->isEmailAddressVerified());
    }

    public function test_a_user_whose_email_address_is_the_username_is_not_verified(): void
    {
        $other = $this->createUnverifiedUser('alice', 'bob');

        $this->expectUserNotFound();
        try {
            $this->createManager([$other])->verifyEmailAddress('bob', 'token');
        } finally {
            self::assertFalse($other->isEmailAddressVerified());
        }
    }

    public function test_a_username_matching_more_than_one_user_is_not_found(): void
    {
        $user = $this->createUnverifiedUser('bob', 'bob@example.com');
        $other = $this->createUnverifiedUser('alice', 'bob');

        $this->expectUserNotFound();
        try {
            $this->createManager([$user, $other])->verifyEmailAddress('bob', 'token');
        } finally {
            self::assertFalse($user->isEmailAddressVerified());
        }
    }

    private function expectUserNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User not found');
    }

    private function createUnverifiedUser(string $username, string $emailAddress): User
    {
        $user = new User($username, $emailAddress);
        $user->setEmailAddressVerifyToken('token');

        return $user;
    }

    /**
     * @param User[] $users
     */
    private function createManager(array $users, ?EntityManagerInterface $entityManager = null): EmailAddressManager
    {
        return new EmailAddressManager(
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            new InMemoryUserRepository($users),
            new PasswordHasherFactory([PasswordAuthenticatedUserInterface::class => ['algorithm' => 'plaintext']]),
            $this->createStub(UserDataProcessor::class),
            $this->createStub(UserEventListener::class),
        );
    }
}
