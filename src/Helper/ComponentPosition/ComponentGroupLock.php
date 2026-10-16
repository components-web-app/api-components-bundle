<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\ComponentPosition;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PropagateUpdatesListener;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;

final class ComponentGroupLock
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly ?PropagateUpdatesListener $propagation = null,
    ) {
    }

    /**
     * @template T
     *
     * @param \Closure(): T $callback
     *
     * @return T
     */
    public function transactional(\Closure $callback): mixed
    {
        $entityManager = $this->entityManager();
        $connection = $entityManager->getConnection();
        $this->propagation?->hold();
        $connection->beginTransaction();
        try {
            $result = $callback();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $this->propagation?->release($entityManager, false);

            throw $exception;
        }
        $this->propagation?->release($entityManager, true);

        return $result;
    }

    /**
     * @param list<ComponentPosition> $touched
     *
     * @return list<ComponentPosition>
     */
    public function lockedPositions(ComponentGroup $group, array $touched = []): array
    {
        $entityManager = $this->entityManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        if (!$this->isStored($unitOfWork, $group)) {
            return [];
        }

        $entityManager->lock($group, LockMode::PESSIMISTIC_WRITE);

        $stored = array_values(array_filter($touched, fn (ComponentPosition $position) => $this->isStored($unitOfWork, $position)));
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('position')
            ->from(ComponentPosition::class, 'position')
            ->where('position.componentGroup = :group')
            ->setParameter('group', $group);
        if ($stored) {
            $queryBuilder
                ->andWhere('position.id NOT IN (:touched)')
                ->setParameter('touched', array_map(static fn (ComponentPosition $position) => (string) $position->getId(), $stored));
        }
        /** @var list<ComponentPosition> $positions */
        $positions = $queryBuilder->getQuery()->setHint(Query::HINT_REFRESH, true)->getResult();

        foreach ($stored as $position) {
            if (($unitOfWork->getOriginalEntityData($position)['componentGroup'] ?? null) === $group) {
                $positions[] = $position;
            }
        }

        usort($positions, static fn (ComponentPosition $a, ComponentPosition $b) => [$a->sortValue, (string) $a->getId()] <=> [$b->sortValue, (string) $b->getId()]);

        return $positions;
    }

    private function isStored(UnitOfWork $unitOfWork, object $entity): bool
    {
        return UnitOfWork::STATE_MANAGED === $unitOfWork->getEntityState($entity) && !$unitOfWork->isScheduledForInsert($entity);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = $this->registry->getManagerForClass(ComponentPosition::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new InvalidArgumentException(\sprintf('No Doctrine ORM entity manager manages %s', ComponentPosition::class));
        }

        return $entityManager;
    }
}
