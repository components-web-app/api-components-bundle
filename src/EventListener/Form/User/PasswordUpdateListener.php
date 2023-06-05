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
use Silverback\ApiComponentsBundle\EventListener\Form\EntityPersistFormListener;
use Silverback\ApiComponentsBundle\Form\Type\User\PasswordUpdateType;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordUpdateListener extends EntityPersistFormListener
{
    public function __construct(private readonly UserDataProcessor $userDataProcessor)
    {
        parent::__construct(PasswordUpdateType::class, AbstractUser::class, false);
    }

    public function __invoke(FormSuccessEvent $event): void
    {
        $formDataUser = $event->getFormData();
        if (
            !$formDataUser instanceof AbstractUser
            || PasswordUpdateType::class !== $event->getForm()->formType
        ) {
            return;
        }

        $user = $this->userDataProcessor->passwordReset($formDataUser->getUsername(), (string) $formDataUser->plainNewPasswordConfirmationToken, (string) $formDataUser->getPlainPassword());

        if (!$user) {
            $event->result = new Response(null, Response::HTTP_NOT_FOUND);

            return;
        }

        parent::__invoke($event);
    }
}
