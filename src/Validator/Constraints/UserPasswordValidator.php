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

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserPasswordValidator extends ConstraintValidator
{
    private TokenStorageInterface $tokenStorage;
    private PasswordHasherFactoryInterface $passwordHasherFactory;
    private UserRepositoryInterface $userRepository;

    public function __construct(TokenStorageInterface $tokenStorage, PasswordHasherFactoryInterface $passwordHasherFactory, UserRepositoryInterface $userRepository)
    {
        $this->tokenStorage = $tokenStorage;
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->userRepository = $userRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($password, Constraint $constraint): void
    {
        if (!$constraint instanceof UserPassword) {
            throw new UnexpectedTypeException($constraint, UserPassword::class);
        }

        if (null === $password || '' === $password) {
            $this->context->addViolation($constraint->message);

            return;
        }

        $databaselessUser = $this->tokenStorage->getToken()->getUser();
        $user = $this->userRepository->find($databaselessUser->getId());

        if (!$user instanceof UserInterface) {
            throw new ConstraintDefinitionException('The User object must implement the UserInterface interface.');
        }

        $hasher = $this->passwordHasherFactory->getPasswordHasher($user);

        if (null === $user->getPassword() || !$hasher->verify($user->getPassword(), $password)) {
            $this->context->addViolation($constraint->message);
        }
    }
}
