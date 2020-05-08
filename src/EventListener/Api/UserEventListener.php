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

namespace Silverback\ApiComponentsBundle\EventListener\Api;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserEventListener
{
    private UserMailer $userMailer;

    public function __construct(UserMailer $userMailer)
    {
        $this->userMailer = $userMailer;
    }

    public function onPostWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        $previousData = $request->attributes->get('previous_data');
        if (
            empty($data) ||
            !$data instanceof AbstractUser ||
            $request->isMethod(Request::METHOD_GET) ||
            $request->isMethod(Request::METHOD_DELETE)
        ) {
            return;
        }

        $this->postWrite($data, !$request->isMethod(Request::METHOD_POST) ? $previousData : null);
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

        if ($previousUser->getNewEmailAddress() !== $user->getNewEmailAddress()) {
            $this->userMailer->sendChangeEmailVerificationEmail($user);
        }
    }
}
