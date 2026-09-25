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

use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Tests\Command\InMemoryUserRepository;
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
        $this->userRepository = new InMemoryUserRepository([]);
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
        $this->userRepository = new InMemoryUserRepository([$storedUser]);
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

    public function test_a_bundle_user_is_not_checked_against_another_user_found_by_the_same_identifier(): void
    {
        $tokenUser = new User('bob', 'bob@example.com', password: 'secret');
        $this->userRepository = new InMemoryUserRepository([new User('alice', 'bob', password: 'alices-password')]);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);
        $this->authenticate($tokenUser);

        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->validate('alices-password', new UserPassword(message: 'wrong'));
    }

    public function test_a_bundle_user_whose_identifier_matches_two_users_cannot_be_checked(): void
    {
        $tokenUser = new User('bob', 'bob@example.com', password: 'secret');
        $this->userRepository = new InMemoryUserRepository([
            new User('bob', 'bob@example.com', password: 'secret'),
            new User('carol', 'bob', password: 'carols-password'),
        ]);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);
        $this->authenticate($tokenUser);

        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->validate('secret', new UserPassword(message: 'wrong'));
    }

    public function test_a_bundle_user_is_matched_regardless_of_username_case(): void
    {
        $tokenUser = new User('Bob', 'bob@example.com', password: 'stale');
        $this->userRepository = new InMemoryUserRepository([new User('bob', 'bob@example.com', password: 'current')]);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);
        $this->authenticate($tokenUser);

        $this->validator->validate('current', new UserPassword(message: 'wrong'));

        $this->assertNoViolation();
    }

    private function authenticate(UserInterface $user): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }
}
