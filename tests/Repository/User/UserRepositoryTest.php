<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\Repository\User;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Silverback\ApiComponentBundle\Repository\User\UserRepository;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;
    private UserRepository $repository;
    /**
     * @var Registry|object|null
     */
    private $managerRegistry;

    private int $passwordResetTimeoutSeconds = 10;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->managerRegistry = $kernel->getContainer()->get('doctrine');
        $this->repository = new UserRepository($this->managerRegistry, $this->passwordResetTimeoutSeconds, User::class);
        $this->entityManager = $this->managerRegistry->getManager();
        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
    }

    public function test_invalid_class(): void
    {
        $this->expectException(LogicException::class);
        new UserRepository($this->managerRegistry, 10, __CLASS__);
    }

    public function test_find_by_email(): void
    {
        $this->assertNull($this->repository->findOneByEmail('email@address.com'));

        $user = new User();
        $user->setUsername('username')->setEmailAddress('email@address.com');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertEquals($user, $this->repository->findOneByEmail('email@address.com'));
    }

    public function test_find_by_password_reset_token(): void
    {
        $username = 'pw_reset@email.com';
        $token = 'pw_token';
        $this->assertNull($this->repository->findOneByPasswordResetToken($username, $token));

        $requestedAt = new \DateTime();
        // persisting in this test can sometimes take more than 2 seconds hence we have checked with an extra delay
        $requestedAt = $requestedAt->modify(sprintf('-%d seconds', $this->passwordResetTimeoutSeconds - 4));

        $user = new User();
        $user
            ->setUsername($username)
            ->setNewPasswordConfirmationToken($token)
            ->setPasswordRequestedAt($requestedAt);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertEquals($user, $this->repository->findOneByPasswordResetToken($username, $token));
    }

    public function test_find_by_expired_password_reset_token(): void
    {
        $username = 'expired_pw_reset@email.com';
        $token = 'expired_pw_token';
        $this->assertNull($this->repository->findOneByPasswordResetToken($username, $token));

        $requestedAt = new \DateTime();
        $requestedAt = $requestedAt->modify(sprintf('-%d seconds', $this->passwordResetTimeoutSeconds));
        $user = new User();
        $user
            ->setUsername($username)
            ->setNewPasswordConfirmationToken($token)
            ->setPasswordRequestedAt($requestedAt);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertNull($this->repository->findOneByPasswordResetToken($username, $token));
    }

    public function test_find_by_email_verification_token(): void
    {
        $username = 'email_verification_username';
        $email = 'email_verification_username@email.com';
        $token = 'email_token';
        $this->assertNull($this->repository->findOneByEmailVerificationToken($username, $email, $token));

        $user = new User();
        $user
            ->setUsername($username)
            ->setEmailAddress($email)
            ->setNewEmailAddress('new@email.com')
            ->setNewEmailVerificationToken($token);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertEquals($user, $this->repository->findOneByEmailVerificationToken($username, $email, $token));
    }

    public function test_load_user_by_username(): void
    {
        $username = 'unique_username';
        $email = 'unique@email.com';
        $this->assertNull($this->repository->loadUserByUsername('does_not_exist'));

        $user = new User();
        $user
            ->setUsername($username)
            ->setEmailAddress($email);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertEquals($user, $this->repository->loadUserByUsername($username));
        $this->assertEquals($user, $this->repository->loadUserByUsername($email));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null; // avoid memory leaks
    }
}
