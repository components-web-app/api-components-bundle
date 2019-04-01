<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventSubscriber\EntitySubscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent;
use Silverback\ApiComponentBundle\Entity\Content\Page\StaticPage;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Factory\RouteFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteAwareSubscriber implements EntitySubscriberInterface
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
            'preFlush',
            'preRemove'
        ];
    }

    public function supportsEntity($entity = null): bool
    {
        return $entity instanceof RouteAwareInterface;
    }

    /**
     * @param LifecycleEventArgs $eventArgs
     * @param RouteAwareInterface $entity
     */
    public function prePersist(LifecycleEventArgs $eventArgs, RouteAwareInterface $entity): void
    {
        $this->prePersistUpdate($eventArgs->getEntityManager(), $entity);
    }

    /**
     * @param PreUpdateEventArgs $eventArgs
     * @param RouteAwareInterface $entity
     */
    public function preUpdate(PreUpdateEventArgs $eventArgs, RouteAwareInterface $entity): void
    {
        $this->prePersistUpdate($eventArgs->getEntityManager(), $entity);
    }

    /**
     * @param mixed $entity
     * @param EntityManager $em
     */
    public function prePersistUpdate(EntityManager $em, RouteAwareInterface $entity): void
    {
        $this->routeFactory->createPageRoute($entity, $em);
    }

    public function preRemove(LifecycleEventArgs $eventArgs, RouteAwareInterface $entity): void
    {
        $em = $eventArgs->getEntityManager();
        $routes = $entity->getRoutes();
        foreach ($routes as $route) {
            if (!$route->getRedirect()) {
                if (($entity instanceof StaticPage) && !$route->getDynamicContent() && $route->getStaticPage() === $entity) {
                    $em->remove($route);
                } elseif (($entity instanceof DynamicContent) && !$route->getStaticPage() && $route->getDynamicContent() === $entity) {
                    $em->remove($route);
                }
            }
        }
    }

    /**
     * @param PreFlushEventArgs $eventArgs
     */
    public function preFlush(PreFlushEventArgs $eventArgs): void
    {
        $em = $eventArgs->getEntityManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($this->routeFactory->createPageRoute($entity)) {
                $pageClassMetaData = $em->getClassMetadata(\get_class($entity));
                $uow = $em->getUnitOfWork();
                $uow->recomputeSingleEntityChangeSet($pageClassMetaData, $entity);
            }
        }
    }
}
