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
    private ?array $changeSet = null;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();
        if (!$entity instanceof AbstractUser) {
            return;
        }

        $this->encodePassword($entity);
    }

    public function postPersist(): void
    {
        // send new user notification email to admin if enabled (it is enabled by default)

        // send welcome email to new user if enabled (it is enabled by default)
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();
        if (!$entity instanceof AbstractUser) {
            return;
        }

        $this->encodePassword($entity);

        $this->changeSet = $args->getEntityManager()->getUnitOfWork()->getEntityChangeSet($entity);
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof AbstractUser) {
            return;
        }

        if (!$this->changeSet) {
            return;
        }

        $isNewlyEnabled = $entity->isEnabled() && isset($this->changeSet['enabled']) && !$this->changeSet['enabled'][0];
        if ($isNewlyEnabled) {
            // send notification email to user
        }

        $isUsernameChanged = isset($this->changeSet['username']);
        if ($isUsernameChanged) {
            // send notification email to user
        }

        $isPasswordChanged = (bool) $entity->getPlainPassword();
        if ($isPasswordChanged) {
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
