<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventListener\Form;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentBundle\Form\Type\NewEmailAddressType;
use Silverback\ApiComponentBundle\Mailer\UserMailer;
use Silverback\ApiComponentBundle\Security\TokenGenerator;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class NewEmailAddressListener
{
    private UserMailer $userMailer;
    private TokenGenerator $tokenGenerator;
    private EntityManagerInterface $entityManager;

    public function __construct(UserMailer $userMailer, TokenGenerator $tokenGenerator, EntityManagerInterface $entityManager)
    {
        $this->userMailer = $userMailer;
        $this->tokenGenerator = $tokenGenerator;
        $this->entityManager = $entityManager;
    }

    public function __invoke(FormSuccessEvent $event)
    {
        if (NewEmailAddressType::class !== $event->getFormResource()->formType) {
            return;
        }
        $user = $event->getForm()->getData();
        if (!$user instanceof AbstractUser) {
            return;
        }

        $user->setNewEmailConfirmationToken($confirmationToken = $this->tokenGenerator->generateToken());
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->userMailer->sendChangeEmailConfirmationEmail($user);
    }
}
