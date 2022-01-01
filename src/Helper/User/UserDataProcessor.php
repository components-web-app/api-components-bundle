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

namespace Silverback\ApiComponentsBundle\Helper\User;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Security\TokenGenerator;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserDataProcessor
{
    private UserPasswordHasherInterface $passwordHasher;
    private UserRepositoryInterface $userRepository;
    private PasswordHasherFactoryInterface $passwordHasherFactory;
    private bool $initialEmailVerifiedState;
    private bool $verifyEmailOnRegister;
    private bool $verifyEmailOnChange;
    private int $tokenTtl;

    public function __construct(
        UserPasswordHasherInterface    $passwordHasher,
        UserRepositoryInterface        $userRepository,
        PasswordHasherFactoryInterface $passwordHasherFactory,
        bool                           $initialEmailVerifiedState,
        bool                           $verifyEmailOnRegister,
        bool                           $verifyEmailOnChange,
        int                            $tokenTtl = 8600
    ) {
        $this->passwordHasher = $passwordHasher;
        $this->userRepository = $userRepository;
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->initialEmailVerifiedState = $initialEmailVerifiedState;
        $this->verifyEmailOnRegister = $verifyEmailOnRegister;
        $this->verifyEmailOnChange = $verifyEmailOnChange;
        $this->tokenTtl = $tokenTtl;
    }

    public function updatePasswordConfirmationToken(string $usernameQuery): ?AbstractUser
    {
        $user = $this->userRepository->findOneBy(['username' => $usernameQuery]);
        if (!$user) {
            throw new InvalidArgumentException('Username not found');
        }

        if ($user->isPasswordRequestLimitReached($this->tokenTtl)) {
            return null;
        }

        $username = $user->getUsername();
        if (!$username) {
            throw new UnexpectedValueException(sprintf('The entity %s should have a username set to send a password reset email.', AbstractUser::class));
        }
        $user->setNewPasswordConfirmationToken($this->passwordHasher->hashPassword($user, $token = TokenGenerator::generateToken()));
        $user->plainNewPasswordConfirmationToken = $token;
        $user->setPasswordRequestedAt(new \DateTime());

        return $user;
    }

    public function passwordReset(string $username, string $token, string $newPassword): ?AbstractUser
    {
        $user = $this->userRepository->findOneWithPasswordResetToken($username);
        if (!$user) {
            return null;
        }
        $hasher = $this->passwordHasherFactory->getPasswordHasher($user);
        if (!$hasher->verify($user->getNewPasswordConfirmationToken(), $token)) {
            return null;
        }

        $user->setPlainPassword($newPassword);
        $user->setNewPasswordConfirmationToken(null);
        $user->setPasswordRequestedAt(null);
        $this->hashPassword($user);

        return $user;
    }

    public function processChanges(AbstractUser $user, ?AbstractUser $previousUser): void
    {
        $this->hashPassword($user);
        if (!$previousUser) {
            $user->setEmailAddressVerified($this->initialEmailVerifiedState);
            if (!$this->initialEmailVerifiedState && $this->verifyEmailOnRegister) {
                $user->setEmailAddressVerifyToken($this->passwordHasher->hashPassword($user, $token = TokenGenerator::generateToken()));
                $user->plainEmailAddressVerifyToken = $token;
            }
        }

        if ($previousUser) {
            if ($this->verifyEmailOnChange && $user->getEmailAddress() !== $previousUser->getEmailAddress()) {
                $user->setEmailAddressVerifyToken($this->passwordHasher->hashPassword($user, $token = TokenGenerator::generateToken()));
                $user->plainEmailAddressVerifyToken = $token;
            }
            if (($newEmail = $user->getNewEmailAddress()) !== $previousUser->getNewEmailAddress()) {
                if ($newEmail) {
                    $user->setNewEmailConfirmationToken($this->passwordHasher->hashPassword($user, $token = TokenGenerator::generateToken()));
                    $user->plainNewEmailConfirmationToken = $token;
                } else {
                    // invalidate any existing requests
                    $user->setNewEmailConfirmationToken(null);
                }
            }
        }
    }

    private function hashPassword(AbstractUser $entity): bool
    {
        if (!$entity->getPlainPassword()) {
            return false;
        }
        $encoded = $this->passwordHasher->hashPassword($entity, $entity->getPlainPassword());
        $entity->setPassword($encoded);
        $entity->eraseCredentials();

        return true;
    }
}
