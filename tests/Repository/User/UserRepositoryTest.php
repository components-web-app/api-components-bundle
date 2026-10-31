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

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;

final class UserRepositoryTest extends OrphanedResourceDatabaseTestCase
{
    /**
     * @return iterable<string, array{\Closure(UserRepository): ?User}>
     */
    public static function lookupsWithSurroundingSpace(): iterable
    {
        yield 'a username identifier' => [static fn (UserRepository $repository) => $repository->loadUserByIdentifier(" My_Username \t")];
        yield 'an email address identifier' => [static fn (UserRepository $repository) => $repository->loadUserByIdentifier(' User@Example.com ')];
        yield 'a username through the deprecated loader' => [static fn (UserRepository $repository) => $repository->loadUserByUsername('MY_username ')];
        yield 'an email address' => [static fn (UserRepository $repository) => $repository->findOneByEmail(' User@Example.COM ')];
        yield 'a username with a password reset token' => [static fn (UserRepository $repository) => $repository->findOneWithPasswordResetToken(' My_Username ')];
        yield 'a username and new email address' => [static fn (UserRepository $repository) => $repository->findOneByUsernameAndNewEmailAddress(' My_Username ', ' New@Example.com ')];
    }

    #[DataProvider('lookupsWithSurroundingSpace')]
    public function test_a_lookup_ignores_surrounding_whitespace_and_case(\Closure $lookup): void
    {
        $user = $this->persistUser();

        self::assertSame($user, $lookup($this->repository()));
    }

    public function test_an_identifier_that_matches_no_one_finds_no_user(): void
    {
        $this->persistUser();

        self::assertNull($this->repository()->loadUserByIdentifier('my user'));
    }

    private function persistUser(): User
    {
        $user = new User('my_username', 'user@example.com');
        $user
            ->setNewPasswordConfirmationToken('token')
            ->setPasswordRequestedAt(new \DateTime())
            ->setNewEmailAddress('new@example.com')
            ->setNewEmailConfirmationToken('token')
            ->setNewEmailAddressChangeRequestedAt(new \DateTime());
        $this->timestampedDataPersister->persistTimestampedFields($user, true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function repository(): UserRepository
    {
        return new UserRepository($this->registry, User::class, 3600, 3600);
    }
}
