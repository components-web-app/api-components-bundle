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

namespace Silverback\ApiComponentBundle\EventListener\Form\User;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentBundle\Form\Type\User\UserRegisterType;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserRegisterListener
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function __invoke(FormSuccessEvent $event)
    {
        if (
            UserRegisterType::class !== $event->getFormResource()->formType ||
            !($user = $event->getForm()->getData()) instanceof AbstractUser
        ) {
            return;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $event->response = $user;
    }
}
