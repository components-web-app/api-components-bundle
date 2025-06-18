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
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        private readonly bool $initialEmailVerifiedState,
        private readonly bool $verifyEmailOnRegister,
        private readonly bool $verifyEmailOnChange,
        private readonly int $tokenTtl = 8600,
    ) {
    }

    public function updatePasswordConfirmationToken(string $usernameQuery): ?AbstractUser
    {
        $user = $this->userRepository->loadUserByIdentifier($usernameQuery);
        if (!$user) {
            throw new InvalidArgumentException('Username not found');
        }

        if ($user->isPasswordRequestLimitReached($this->tokenTtl)) {
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
                    if ($previousUser->getNewEmailAddress() === $user->getEmailAddress() && $user->plainEmailAddressVerifyToken) {
                        $user->plainEmailAddressVerifyToken = null;
                        $user->setEmailAddressVerifyToken(null);
                    }
                }
            }
        }
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
