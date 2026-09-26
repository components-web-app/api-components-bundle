<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\EventListener\Api;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;

/**
 * @author Daniel West <daniel@silverback.is>
 */
readonly class UserEventListener
{
    public function __construct(
        private UserMailer $userMailer,
    ) {
    }

    public function postWrite(AbstractUser $user, ?AbstractUser $previousUser): void
    {
        if (!$previousUser) {
            $this->userMailer->sendWelcomeEmail($user);

            return;
        }

        if (!$previousUser->isEnabled() && $user->isEnabled()) {
            $this->userMailer->sendUserEnabledEmail($user);
        }

        if ($previousUser->getUsername() !== $user->getUsername()) {
            $this->userMailer->sendUsernameChangedEmail($user);
        }

        if ($previousUser->getPassword() !== $user->getPassword()) {
            $this->userMailer->sendPasswordChangedEmail($user);
        }

        if ($user->plainEmailAddressVerifyToken && ($token = $user->getEmailAddressVerifyToken()) && $token !== $previousUser->getEmailAddressVerifyToken()) {
            $this->userMailer->sendEmailVerifyEmailAfterWrite($user);
        }

        if (($token = $user->getNewEmailConfirmationToken()) && $token !== $previousUser->getNewEmailConfirmationToken()) {
            $this->userMailer->sendChangeEmailConfirmationEmailAfterWrite($user);
        }
    }
}
