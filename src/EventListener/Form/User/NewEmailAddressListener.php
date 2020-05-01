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

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class NewEmailAddressListener
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function __invoke(FormSuccessEvent $event)
    {
        if (
            NewEmailAddressType::class !== $event->getFormResource()->formType ||
            !($user = $event->getForm()->getData()) instanceof AbstractUser
        ) {
            return;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
