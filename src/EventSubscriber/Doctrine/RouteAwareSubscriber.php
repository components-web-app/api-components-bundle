<?php

namespace Silverback\ApiComponentBundle\EventSubscriber\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Factory\Entity\Route\RouteFactory;

/**
 *
 * @author Daniel West <daniel@silverback.is>
 */
class RouteAwareSubscriber implements EventSubscriber
{
    /**
     * @var RouteFactory
     */
    private $routeFactory;

    public function __construct(
        RouteFactory $routeFactory
    ) {
        $this->routeFactory = $routeFactory;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        return [
            'prePersist',
            'preUpdate',
            'preFlush'
        ];
    }

    /**
     * @param LifecycleEventArgs $eventArgs
     * @throws \Doctrine\ORM\ORMException
     */
    public function prePersist(LifecycleEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getEntity();
        $this->prePersistUpdate($entity, $eventArgs->getEntityManager());
    }

    /**
     * @param PreUpdateEventArgs $eventArgs
     * @throws \Doctrine\ORM\ORMException
     */
    public function preUpdate(PreUpdateEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getEntity();
        $this->prePersistUpdate($entity, $eventArgs->getEntityManager());
    }

    /**
     * @param mixed $entity
     * @param EntityManager $em
     * @throws \Doctrine\ORM\ORMException
     */
    public function prePersistUpdate($entity, EntityManager $em): void
    {
        if (
            $entity instanceof RouteAwareInterface &&
            $route = $this->createPageRoute($entity)
        ) {
            $em->persist($route);
        }
    }

    /**
     * @param PreFlushEventArgs $eventArgs
     * @throws \Doctrine\ORM\ORMException
     */
    public function preFlush(PreFlushEventArgs $eventArgs): void
    {
        $em = $eventArgs->getEntityManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (
                $entity instanceof RouteAwareInterface &&
                $route = $this->createPageRoute($entity)
            ) {
                $pageClassMetaData = $em->getClassMetadata(\get_class($entity));
                $uow = $em->getUnitOfWork();
                $uow->recomputeSingleEntityChangeSet($pageClassMetaData, $entity);
                $em->persist($route);
            }
        }
    }

    /**
     * @param RouteAwareInterface $entity
     * @return null|Route
     */
    private function createPageRoute(RouteAwareInterface $entity): ?Route
    {
        if (0 === $entity->getRoutes()->count()) {
            return $this->routeFactory->createFromRouteAwareEntity($entity);
        }
        return null;
    }
}
