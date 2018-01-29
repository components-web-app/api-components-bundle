<?php

namespace Silverback\ApiComponentBundle\EntityListener;

use Silverback\ApiComponentBundle\Entity\Page;
use Silverback\ApiComponentBundle\Factory\RouteFactory;
use Doctrine\Common\EventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;

class PageListener
{
    /**
     * @var RouteFactory
     */
    private $routeFactory;

    public function __construct(
        RouteFactory $routeFactory
    )
    {
        $this->routeFactory = $routeFactory;
    }

    /**
     * @ORM\PrePersist()
     * @param Page $page
     * @param LifecycleEventArgs $event
     */
    public function prePersist (Page $page, LifecycleEventArgs $event): void
    {
        $this->createPageRoute($page, $event);
    }

    /**
     * @ORM\PreUpdate()
     * @param Page $page
     * @param PreUpdateEventArgs $event
     */
    public function preUpdate (Page $page, PreUpdateEventArgs $event): void
    {
        $this->createPageRoute($page, $event);
    }

    /**
     * @ORM\PreFlush()
     * @param Page $page
     * @param PreFlushEventArgs $eventArgs
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     */
    public function preFlush (Page $page, PreFlushEventArgs $eventArgs): void
    {
        if ($this->createPageRoute($page, $eventArgs)) {
            $em = $eventArgs->getEntityManager();
            $pageClassMetaData = $em->getClassMetadata(Page::class);
            $uow = $em->getUnitOfWork();
            $uow->recomputeSingleEntityChangeSet($pageClassMetaData, $page);
        }
    }

    private function createPageRoute(Page $page, EventArgs $event): bool
    {
        if (0 === $page->getRoutes()->count()) {
            $this->routeFactory->create($page);
            return true;
        }
        return false;
    }
}
