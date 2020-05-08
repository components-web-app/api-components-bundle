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
use Silverback\ApiComponentsBundle\Security\TokenGenerator;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserChangesProcessor
{
    private UserPasswordEncoderInterface $passwordEncoder;
    private bool $initialEmailVerifiedState;
    private bool $verifyEmailOnRegister;
    private bool $verifyEmailOnChange;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder, bool $initialEmailVerifiedState, bool $verifyEmailOnRegister, bool $verifyEmailOnChange)
    {
        $this->passwordEncoder = $passwordEncoder;
        $this->initialEmailVerifiedState = $initialEmailVerifiedState;
        $this->verifyEmailOnRegister = $verifyEmailOnRegister;
        $this->verifyEmailOnChange = $verifyEmailOnChange;
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
