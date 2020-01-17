<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventSubscriber\EntitySubscriber;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Silverback\ApiComponentBundle\Entity\User\User;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserSubcriber implements EntitySubscriberInterface
{
    private $passwordEncoder;

    public function __construct(
        UserPasswordEncoderInterface $passwordEncoder
    ) {
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist => 'prePersist',
            Events::preUpdate => 'preUpdate'
        ];
    }

    public function supportsEntity($entity = null): bool
    {
        return $entity instanceof User;
    }

    public function prePersist(LifecycleEventArgs $eventArgs, User $entity): void
    {
        $this->prePersistUpdate($eventArgs->getEntityManager(), $entity);
    }

    public function preUpdate(PreUpdateEventArgs $eventArgs, User $entity): void
    {
        $this->prePersistUpdate($eventArgs->getEntityManager(), $entity);
    }

    public function prePersistUpdate(EntityManager $em, User $entity): void
    {
        if ($entity->getPlainPassword()) {
            $password = $this->passwordEncoder->encodePassword($entity, $entity->getPlainPassword());
            $entity->setPassword($password);
        }
    }
}
