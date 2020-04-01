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

namespace Silverback\ApiComponentBundle\EventListener\Doctrine;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserListener
{
    private UserPasswordEncoderInterface $passwordEncoder;
    private array $changeSet = [];

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function prePersist(AbstractUser $user): void
    {
        $this->encodePassword($user);
    }

    public function postPersist(): void
    {
        // send new user notification email to admin if enabled (it is enabled by default)

        // send welcome email to new user if enabled (it is enabled by default)
    }

    public function preUpdate(AbstractUser $user, LifecycleEventArgs $args): void
    {
        $this->encodePassword($user);

        $this->changeSet = $args->getEntityManager()->getUnitOfWork()->getEntityChangeSet($user);
    }

    public function postUpdate(AbstractUser $user): void
    {
        if (isset($this->changeSet['enabled']) && !$this->changeSet['enabled'][0] && $user->isEnabled()) {
            // send notification email to user
        }

        if (isset($this->changeSet['username'])) {
            // send notification email to user
        }

        if (isset($this->changeSet['password'])) {
            // send notification email to user
        }
    }

    private function encodePassword(AbstractUser $entity): void
    {
        if (!$entity->getPlainPassword()) {
            return;
        }
        $encoded = $this->passwordEncoder->encodePassword(
            $entity,
            $entity->getPlainPassword()
        );
        $entity->setPassword($encoded);
        $entity->eraseCredentials();
    }
}
