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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
readonly class UserEventListener
{
    public function __construct(
        private UserMailer $userMailer,
        private Security $security,
    ) {
    }

    public function onPreRead(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $resourceClass = $request->attributes->get('_api_resource_class');
        if (
            empty($resourceClass)
            || !is_a($resourceClass, AbstractUser::class, true)
            || '_api_me' !== $request->attributes->get('_api_operation_name')
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

        $request->attributes->set('id', $user->getUsername());
    }

    public function onPostRead(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $class = $request->attributes->get('_api_resource_class');

        if (AbstractUser::class === $class) {
            $resources = [
                '/me',
            ];

            $request->attributes->set('_resources', $request->attributes->get('_resources', []) + $resources);
        }
    }

    public function onPostWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        $previousData = $request->attributes->get('previous_data');
        if (
            empty($data)
            || !$data instanceof AbstractUser
            || $request->isMethod(Request::METHOD_GET)
            || $request->isMethod(Request::METHOD_DELETE)
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

        if ($user->plainEmailAddressVerifyToken && ($token = $user->getEmailAddressVerifyToken()) && $token !== $previousUser->getEmailAddressVerifyToken()) {
            $this->userMailer->sendEmailVerifyEmailAfterWrite($user);
        }

        if (($token = $user->getNewEmailConfirmationToken()) && $token !== $previousUser->getNewEmailConfirmationToken()) {
            $this->userMailer->sendChangeEmailConfirmationEmailAfterWrite($user);
        }
    }
}
