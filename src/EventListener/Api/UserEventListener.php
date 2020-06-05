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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserEventListener
{
    private UserMailer $userMailer;
    private Security $security;

    public function __construct(UserMailer $userMailer, Security $security)
    {
        $this->userMailer = $userMailer;
        $this->security = $security;
    }

    public function onPreRead(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $resourceClass = $request->attributes->get('_api_resource_class');
        if (
            empty($resourceClass) ||
            !is_a($resourceClass, AbstractUser::class, true) ||
            'me' !== $request->attributes->get('_api_item_operation_name')
        ) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user) {
            throw new AccessDeniedException('Access denied.');
        }
        if (!$user instanceof AbstractUser) {
            throw new AccessDeniedException('Access denied. User not supported.');
        }
        $request->attributes->set('id', $user->getId());
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

        if (($token = $user->getEmailAddressVerifyToken()) && $token !== $previousUser->getEmailAddressVerifyToken()) {
            $this->userMailer->sendEmailVerifyEmail($user);
        }

        if (($token = $user->getNewEmailConfirmationToken()) && $token !== $previousUser->getNewEmailConfirmationToken()) {
            $this->userMailer->sendChangeEmailConfirmationEmail($user);
        }
    }
}
