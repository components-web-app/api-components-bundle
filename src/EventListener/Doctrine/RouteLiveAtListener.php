<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\EventListener\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteLiveResolver;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class RouteLiveAtListener
{
    private const array ROUTE_TRIGGER_FIELDS = ['liveAt', 'page', 'pageData'];
    private const array PAGE_TRIGGER_FIELDS = ['route', 'parentPage', 'parentPageData'];

    public function __construct(private readonly RouteLiveResolver $resolver)
    {
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $em = $eventArgs->getObjectManager();
        $uow = $em->getUnitOfWork();

        /** @var array<int, array{0: Route, 1: ?AbstractPage}> $pending */
        $pending = [];

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->collect($entity, $pending, null);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->collect($entity, $pending, $uow->getEntityChangeSet($entity));
        }

        if (!$pending) {
            return;
        }

        $this->expandToDescendants($pending, $em);
        $this->applyResolvedValues($pending, $em, $uow);
    }

    /**
     * @param array<int, array{0: Route, 1: ?AbstractPage}> $pending
     */
    private function collect(object $entity, array &$pending, ?array $changeSet): void
    {
        if ($entity instanceof Route) {
            if (null === $changeSet || $this->isTriggered($changeSet, self::ROUTE_TRIGGER_FIELDS)) {
                $this->add($pending, $entity, null);
            }

            return;
        }

        if (!$entity instanceof AbstractPage) {
            return;
        }

        if (null !== $changeSet && !$this->isTriggered($changeSet, self::PAGE_TRIGGER_FIELDS)) {
            return;
        }

        if ($route = $entity->getRoute()) {
            $this->add($pending, $route, $entity);
        }
    }

    private function isTriggered(array $changeSet, array $fields): bool
    {
        foreach ($fields as $field) {
            if (\array_key_exists($field, $changeSet)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{0: Route, 1: ?AbstractPage}> $pending
     */
    private function add(array &$pending, Route $route, ?AbstractPage $page): void
    {
        $key = spl_object_id($route);
        if (isset($pending[$key]) && null === $page) {
            return;
        }
        $pending[$key] = [$route, $page];
    }

    /**
     * @param array<int, array{0: Route, 1: ?AbstractPage}> $pending
     */
    private function expandToDescendants(array &$pending, EntityManagerInterface $em): void
    {
        $queue = array_values($pending);
        $visitedPageIds = [];

        while ($current = array_shift($queue)) {
            [$route, $page] = $current;
            $page ??= $route->getPage() ?? $route->getPageData();
            if (null === $page) {
                continue;
            }

            $pageId = $page->getId()?->toString();
            if (null !== $pageId) {
                if (isset($visitedPageIds[$pageId])) {
                    continue;
                }
                $visitedPageIds[$pageId] = true;
            }

            foreach ($this->findDirectChildren($page, $em) as $child) {
                $childRoute = $child->getRoute();
                if (null === $childRoute) {
                    continue;
                }
                $this->add($pending, $childRoute, $child);
                $queue[] = [$childRoute, $child];
            }
        }
    }

    /**
     * @return AbstractPage[]
     */
    private function findDirectChildren(AbstractPage $parent, EntityManagerInterface $em): array
    {
        if (null === $parent->getId()) {
            return [];
        }

        $field = $parent instanceof Page ? 'parentPage' : 'parentPageData';

        return array_merge(
            $em->getRepository(Page::class)->findBy([$field => $parent]),
            $em->getRepository(AbstractPageData::class)->findBy([$field => $parent]),
        );
    }

    /**
     * @param array<int, array{0: Route, 1: ?AbstractPage}> $pending
     */
    private function applyResolvedValues(array $pending, EntityManagerInterface $em, UnitOfWork $uow): void
    {
        $classMetadata = $em->getClassMetadata(Route::class);

        foreach ($pending as [$route, $page]) {
            $resolved = $this->resolver->resolveEffectiveLiveAt($route, $page);
            $current = $route->getEffectiveLiveAt();

            if ($resolved == $current) {
                continue;
            }

            $route->setEffectiveLiveAt($resolved);

            if (UnitOfWork::STATE_MANAGED === $uow->getEntityState($route, UnitOfWork::STATE_NEW)) {
                $uow->recomputeSingleEntityChangeSet($classMetadata, $route);
            }
        }
    }
}
