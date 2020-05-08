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

namespace Silverback\ApiComponentsBundle\EventListener\Form\User;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentsBundle\Form\Type\User\PasswordUpdateType;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordUpdateListener
{
    private UserDataProcessor $userDataProcessor;
    private UserMailer $userMailer;

    public function __construct(UserDataProcessor $userDataProcessor, UserMailer $userMailer)
    {
        $this->userDataProcessor = $userDataProcessor;
        $this->userMailer = $userMailer;
    }

    public function __invoke(FormSuccessEvent $event)
    {
        $formDataUser = $event->getFormData();
        if (
            !$formDataUser instanceof AbstractUser ||
            PasswordUpdateType::class !== $event->getForm()->formType
        ) {
            return;
        }

        $user = $this->userDataProcessor->passwordReset((string) $formDataUser->getUsername(), (string) $formDataUser->getNewPasswordConfirmationToken(), (string) $formDataUser->getPlainPassword());
        if (!$user) {
            $event->result = new Response(null, Response::HTTP_NOT_FOUND);

            return;
        }

        $this->userMailer->sendPasswordChangedEmail($user);
        $event->result = new Response(null, Response::HTTP_OK);
    }
}
