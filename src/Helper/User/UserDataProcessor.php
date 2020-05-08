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
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Silverback\ApiComponentsBundle\Security\TokenGenerator;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserDataProcessor
{
    private UserPasswordEncoderInterface $passwordEncoder;
    private UserRepository $userRepository;
    private bool $initialEmailVerifiedState;
    private bool $verifyEmailOnRegister;
    private bool $verifyEmailOnChange;
    private int $tokenTtl;

    public function __construct(
        UserPasswordEncoderInterface $passwordEncoder,
        UserRepository $userRepository,
        bool $initialEmailVerifiedState,
        bool $verifyEmailOnRegister,
        bool $verifyEmailOnChange,
        int $tokenTtl = 8600
    ) {
        $this->passwordEncoder = $passwordEncoder;
        $this->userRepository = $userRepository;
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
        $user->setNewPasswordConfirmationToken(TokenGenerator::generateToken());
        $user->setPasswordRequestedAt(new \DateTime());

        return $user;
    }

    public function passwordReset(string $username, string $token, string $newPassword): ?AbstractUser
    {
        $user = $this->userRepository->findOneByPasswordResetToken($username, $token);
        if (!$user) {
            return null;
        }

        $user->setPlainPassword($newPassword);
        $user->setNewPasswordConfirmationToken(null);
        $user->setPasswordRequestedAt(null);
        $this->encodePassword($user);

        return $user;
    }

    public function processChanges(AbstractUser $user, ?AbstractUser $previousUser): void
    {
        $this->encodePassword($user);
        $user->setEmailAddressVerified($this->initialEmailVerifiedState);

        if (!$previousUser && !$this->initialEmailVerifiedState) {
            $user->setNewEmailAddress($user->getEmailAddress());
            if ($this->verifyEmailOnRegister) {
                $user->setNewEmailVerificationToken(TokenGenerator::generateToken());
            }
        }

        if ($previousUser && $previousUser->getNewEmailAddress() !== ($newEmail = $user->getNewEmailAddress())) {
            if ($this->verifyEmailOnChange) {
                $user->setNewEmailVerificationToken(TokenGenerator::generateToken());
            } else {
                $user->setEmailAddress($newEmail);
                $user->setNewEmailAddress(null);
            }
        }
    }

    private function encodePassword(AbstractUser $entity): bool
    {
        if (!$entity->getPlainPassword()) {
            return false;
        }
        $encoded = $this->passwordEncoder->encodePassword($entity, $entity->getPlainPassword());
        $entity->setPassword($encoded);
        $entity->eraseCredentials();

        return true;
    }
}
