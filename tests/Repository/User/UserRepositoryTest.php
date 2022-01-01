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

namespace Silverback\ApiComponentsBundle\Tests\Repository\User;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Repository\AbstractRepositoryTest;
use Symfony\Component\HttpFoundation\Request;

class UserRepositoryTest extends AbstractRepositoryTest
{
    private UserRepository $repository;
    /**
     * @var Registry|object|null
     */
    private $managerRegistry;

    private int $passwordResetTimeoutSeconds = 10;
    private int $changeEmailTimeoutSeconds = 10;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = $kernel->getContainer();
        $this->managerRegistry = $container->get('doctrine');
        $this->clearSchema($this->managerRegistry);

        $requestStack = $container->get('request_stack');
        $request = new Request();
        $request->headers->set('origin', 'http://test.com');
        $requestStack->push($request);

        $this->repository = new UserRepository($this->managerRegistry, User::class, $this->passwordResetTimeoutSeconds, $this->changeEmailTimeoutSeconds);
    }

    public function test_invalid_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UserRepository($this->managerRegistry, __CLASS__, $this->passwordResetTimeoutSeconds, $this->changeEmailTimeoutSeconds);
    }

    public function test_find_by_email(): void
    {
        $this->assertNull($this->repository->findOneByEmail('email@address.com'));

        $user = new User();
        $user->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $user->setUsername('username')->setEmailAddress('email@address.com');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertEquals($user, $this->repository->findOneByEmail('email@address.com'));
    }

    public function test_find_by_password_reset_token(): void
    {
        $username = 'pw_reset@email.com';
        $token = 'pw_token';
        $this->assertNull($this->repository->findOneWithPasswordResetToken($username));

        $requestedAt = new \DateTime();
        // persisting in this test can sometimes take more than 2 seconds hence we have checked with an extra delay
        $requestedAt = $requestedAt->modify(sprintf('-%d seconds', $this->passwordResetTimeoutSeconds - 4));

        $user = new User();
        $user->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $user
            ->setUsername($username)
            ->setNewPasswordConfirmationToken($token)
            ->setPasswordRequestedAt($requestedAt);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertEquals($user, $this->repository->findOneWithPasswordResetToken($username));
    }

    public function test_find_by_expired_password_reset_token(): void
    {
        $username = 'expired_pw_reset@email.com';
        $token = 'expired_pw_token';
        $this->assertNull($this->repository->findOneWithPasswordResetToken($username));

        $requestedAt = new \DateTime();
        $requestedAt = $requestedAt->modify(sprintf('-%d seconds', $this->passwordResetTimeoutSeconds));
        $user = new User();
        $user->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $user
            ->setUsername($username)
            ->setNewPasswordConfirmationToken($token)
            ->setPasswordRequestedAt($requestedAt);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertNull($this->repository->findOneWithPasswordResetToken($username));
    }

    public function test_find_by_email_confirmation_token(): void
    {
        $username = 'email_confirmation_username';
        $email = 'email_confirmation_username@email.com';
        $token = 'email_token';
        $this->assertNull($this->repository->findOneByUsernameAndNewEmailAddress($username, $email));

        $user = new User();
        $user->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $user
            ->setUsername($username)
            ->setEmailAddress($email)
            ->setNewEmailAddress('new@email.com')
            ->setNewEmailConfirmationToken($token);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertEquals($user, $this->repository->findOneByUsernameAndNewEmailAddress($username, 'new@email.com'));
    }

    public function test_find_by_expired_email_confirmation_token(): void
    {
        $repository = new UserRepository($this->managerRegistry, User::class, $this->passwordResetTimeoutSeconds, 0);

        $username = 'email_confirmation_username';
        $email = 'email_confirmation_username@email.com';
        $token = 'email_token';

        $user = new User();
        $user->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $user
            ->setUsername($username)
            ->setEmailAddress($email)
            ->setNewEmailAddress('new@email.com')
            ->setNewEmailConfirmationToken($token);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertNull($repository->findOneByUsernameAndNewEmailAddress($username, 'new@email.com'));
    }

    public function test_load_user_by_username(): void
    {
        $username = 'unique_username';
        $email = 'unique@email.com';
        $this->assertNull($this->repository->loadUserByIdentifier('does_not_exist'));

        $user = new User();
        $user->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $user
            ->setUsername($username)
            ->setEmailAddress($email);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertEquals($user, $this->repository->loadUserByIdentifier($username));
        $this->assertEquals($user, $this->repository->loadUserByIdentifier($email));
    }

    public function test_find_existing_user_by_new_email(): void
    {
        $username = 'username';

        $userA = new User();
        $userA->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $userA
            ->setUsername($username)
            ->setEmailAddress('usera@address.com')
            ->setNewEmailAddress(null);
        $this->entityManager->persist($userA);

        $userB = new User();
        $userB->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $userB
            ->setUsername($username)
            ->setEmailAddress('userb@address.com')
            ->setNewEmailAddress('usera@address.com');
        $this->entityManager->persist($userB);

        $userC = new User();
        $userC->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $userC
            ->setUsername($username)
            ->setEmailAddress('userc@address.com')
            ->setNewEmailAddress('userc@address.com');
        $this->entityManager->persist($userC);

        $this->entityManager->flush();

        $this->assertNull($this->repository->findExistingUserByNewEmail($userA));
        $this->assertEquals($userA, $this->repository->findExistingUserByNewEmail($userB));
        $this->assertNull($this->repository->findExistingUserByNewEmail($userC));
    }
}
