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
use Silverback\ApiComponentsBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedHelper;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserRegisterListener
{
    private EntityManagerInterface $entityManager;
    private TimestampedHelper $timestampedHelper;

    public function __construct(EntityManagerInterface $entityManager, TimestampedHelper $timestampedHelper)
    {
        $this->entityManager = $entityManager;
        $this->timestampedHelper = $timestampedHelper;
    }

    public function __invoke(FormSuccessEvent $event)
    {
        if (
            UserRegisterType::class !== $event->getFormResource()->formType ||
            !($user = $event->getForm()->getData()) instanceof AbstractUser
        ) {
            return;
        }
        $this->timestampedHelper->persistTimestampedFields($user, true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $event->response = $user;
    }
}
