<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Validator\Constraints;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Validator\Constraints\UserPasswordValidator;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<UserPasswordValidator>
 */
class UserPasswordValidatorTest extends ConstraintValidatorTestCase
{
    private TokenStorage $tokenStorage;
    private UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
        $this->userRepository = self::createUserRepository([]);
        parent::setUp();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new UserPasswordValidator(
            $this->tokenStorage,
            new PasswordHasherFactory([PasswordAuthenticatedUserInterface::class => ['algorithm' => 'plaintext']]),
            $this->userRepository,
        );
    }

    public function test_a_bundle_user_is_verified_against_the_password_stored_for_it(): void
    {
        $tokenUser = new User('bob', 'bob@example.com', password: 'stale');
        $storedUser = new User('bob', 'bob@example.com', password: 'current');
        $this->userRepository = self::createUserRepository([(string) $tokenUser->getId() => $storedUser]);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);
        $this->authenticate($tokenUser);

        $this->validator->validate('current', new UserPassword(message: 'wrong'));

        $this->assertNoViolation();
    }

    public function test_a_user_that_is_not_a_bundle_user_is_verified_against_its_own_password(): void
    {
        $this->authenticate(new InMemoryUser('admin', 'secret'));

        $this->validator->validate('secret', new UserPassword(message: 'wrong'));

        $this->assertNoViolation();
    }

    public function test_a_wrong_password_for_a_user_that_is_not_a_bundle_user_is_a_violation(): void
    {
        $this->authenticate(new InMemoryUser('admin', 'secret'));

        $this->validator->validate('guess', new UserPassword(message: 'wrong'));

        $this->buildViolation('wrong')->assertRaised();
    }

    public function test_a_bundle_user_that_is_no_longer_stored_cannot_be_checked(): void
    {
        $this->authenticate(new User('bob', 'bob@example.com', password: 'secret'));

        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->validate('secret', new UserPassword(message: 'wrong'));
    }

    public function test_without_an_authenticated_user_the_constraint_cannot_be_checked(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->validate('secret', new UserPassword(message: 'wrong'));
    }

    /**
     * @param array<string, AbstractUser> $users
     */
    private static function createUserRepository(array $users): UserRepositoryInterface
    {
        return new class($users) implements UserRepositoryInterface {
            /**
             * @param array<string, AbstractUser> $users
             */
            public function __construct(private readonly array $users)
            {
            }

            public function find(mixed $id): ?AbstractUser
            {
                return $this->users[(string) $id] ?? null;
            }

            public function findOneByEmail(string $value): ?AbstractUser
            {
                return null;
            }

            public function findOneWithPasswordResetToken(string $username): ?AbstractUser
            {
                return null;
            }

            public function findOneByUsernameAndNewEmailAddress(string $username, string $email): ?AbstractUser
            {
                return null;
            }

            public function loadUserByIdentifier(string $identifier): ?AbstractUser
            {
                return null;
            }

            public function findExistingUserByNewEmail(AbstractUser $user): ?AbstractUser
            {
                return null;
            }
        };
    }

    private function authenticate(UserInterface $user): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }
}
