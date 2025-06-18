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
readonly class UserDataProcessor
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepositoryInterface $userRepository,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
        private bool $initialEmailVerifiedState,
        private bool $verifyEmailOnRegister,
        private bool $verifyEmailOnChange,
        private int $tokenTtl = 8600,
    ) {
    }

    public function updatePasswordConfirmationToken(string $usernameQuery): ?AbstractUser
    {
        $user = $this->findUserByUsername($usernameQuery);
        if (!$user || $user->isPasswordRequestLimitReached($this->tokenTtl)) {
            return null;
        }

        $username = $user->getUsername();
        if (!$username) {
            throw new UnexpectedValueException(\sprintf('The entity %s should have a username set to send a password reset email.', AbstractUser::class));
        }
        $user->setNewPasswordConfirmationToken($this->passwordHasher->hashPassword($user, $token = TokenGenerator::generateToken()));
        $user->plainNewPasswordConfirmationToken = $token;

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
                $this->setEmailAddressVerifyToken($user);
            }
        }

        if ($previousUser) {
            if ($this->verifyEmailOnChange && $user->getEmailAddress() !== $previousUser->getEmailAddress()) {
                $this->setEmailAddressVerifyToken($user);
            }
            if (($newEmail = $user->getNewEmailAddress()) !== $previousUser->getNewEmailAddress()) {
                if ($newEmail) {
                    $this->setNewEmailConfirmationToken($user);
                } else {
                    // invalidate any existing requests
                    $user->setNewEmailConfirmationToken(null);
                    // revert any tokens for the new primary saved email address as this was converted from the update
                    if ($previousUser->getNewEmailAddress() === $user->getEmailAddress() && $user->plainEmailAddressVerifyToken) {
                        $user->plainEmailAddressVerifyToken = null;
                        $user->setEmailAddressVerifyToken(null);
                    }
                }
            }
        }
    }

    private function findUserByUsername(string $usernameQuery): ?AbstractUser
    {
        $user = $this->userRepository->loadUserByIdentifier($usernameQuery);
        if (!$user) {
            throw new InvalidArgumentException('Username not found');
        }

        return $user;
    }

    public function updateNewEmailToken(string $usernameQuery): ?AbstractUser
    {
        $user = $this->findUserByUsername($usernameQuery);
        if (!$user) {
            return null;
        }
        $this->setNewEmailConfirmationToken($user);

        return $user;
    }

    public function updateVerifyEmailToken(string $usernameQuery): ?AbstractUser
    {
        $user = $this->findUserByUsername($usernameQuery);
        if (!$user) {
            return null;
        }
        $this->setEmailAddressVerifyToken($user);

        return $user;
    }

    private function setNewEmailConfirmationToken(AbstractUser $user): void
    {
        $user->setNewEmailConfirmationToken($this->passwordHasher->hashPassword($user, $token = TokenGenerator::generateToken()));
        $user->plainNewEmailConfirmationToken = $token;
    }

    private function setEmailAddressVerifyToken(AbstractUser $user): void
    {
        $user->setEmailAddressVerifyToken($this->passwordHasher->hashPassword($user, $token = TokenGenerator::generateToken()));
        $user->plainEmailAddressVerifyToken = $token;
    }

    private function hashPassword(AbstractUser $entity): void
    {
        if (!$entity->getPlainPassword()) {
            return;
        }
        $encoded = $this->passwordHasher->hashPassword($entity, $entity->getPlainPassword());
        $entity->setPassword($encoded);
        $entity->setEmailAddressVerified(true);
        $entity->eraseCredentials();
    }
}
