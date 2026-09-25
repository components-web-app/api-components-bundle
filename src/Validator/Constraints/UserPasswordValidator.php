<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
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

        $tokenUser = $this->tokenStorage->getToken()?->getUser();
        $user = $tokenUser instanceof AbstractUser ? $this->userRepository->find($tokenUser->getId()) : $tokenUser;

        if (!$user instanceof PasswordAuthenticatedUserInterface) {
            throw new ConstraintDefinitionException(\sprintf('The User object must implement the %s interface.', PasswordAuthenticatedUserInterface::class));
        }

        $hasher = $this->passwordHasherFactory->getPasswordHasher($user);

        if (null === $user->getPassword() || !$hasher->verify($user->getPassword(), $password)) {
            $this->context->addViolation($constraint->message);
        }
    }
}
