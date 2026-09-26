<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedResource;

use ApiPlatform\Metadata\Exception\ExceptionInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceDeletion;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\OrphanedResourceHelper;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

class OrphanedResourceDeleter
{
    use ClassMetadataTrait;

    public const string NOT_FOUND = 'not_found';
    public const string NOT_ORPHANED = 'not_orphaned';

    public function __construct(
        ManagerRegistry $registry,
        private readonly OrphanedResourceDetector $detector,
        private readonly OrphanedResourceHelper $orphanedResourceHelper,
        private readonly IriConverterInterface $iriConverter,
    ) {
        $this->initRegistry($registry);
    }

    /**
     * @param list<string>|null $iris
     */
    public function delete(?array $iris): OrphanedResourceDeletion
    {
        $orphans = [];
        foreach ($this->detector->findOrphans() as $references) {
            $orphans += $references;
        }

        $rejected = [];
        $selected = $orphans;
        if (null !== $iris) {
            $selected = [];
            foreach (array_unique($iris) as $iri) {
                if (isset($orphans[$iri])) {
                    $selected[$iri] = $orphans[$iri];
                    continue;
                }
                $rejected[] = ['iri' => $iri, 'reason' => $this->resolves($iri) ? self::NOT_ORPHANED : self::NOT_FOUND];
            }
        }

        $entityManager = $this->getEntityManager(AbstractComponent::class);
        $deleted = ['componentGroups' => [], 'componentPositions' => [], 'components' => []];
        if (!\count($selected)) {
            return new OrphanedResourceDeletion($deleted, $rejected);
        }

        $unitOfWork = $entityManager->getUnitOfWork();
        foreach ($selected as [$class, $id]) {
            $resource = $entityManager->find($class, $id);
            if (null === $resource || $unitOfWork->isScheduledForDelete($resource)) {
                continue;
            }
            $this->remove($resource, $entityManager);
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $resource) {
            $kind = match (true) {
                $resource instanceof ComponentGroup => 'componentGroups',
                $resource instanceof ComponentPosition => 'componentPositions',
                $resource instanceof AbstractComponent => 'components',
                default => null,
            };
            if (null !== $kind) {
                $deleted[$kind][] = $this->iriConverter->getIriFromResource($resource);
            }
        }
        $entityManager->flush();

        return new OrphanedResourceDeletion(array_map(static function (array $kindIris): array {
            sort($kindIris);

            return $kindIris;
        }, $deleted), $rejected);
    }

    private function remove(object $resource, EntityManagerInterface $entityManager): void
    {
        if ($resource instanceof ComponentGroup) {
            $this->orphanedResourceHelper->handleRemovedComponentGroup($resource);

            return;
        }
        $entityManager->remove($resource);
        if ($resource instanceof ComponentPosition) {
            $this->orphanedResourceHelper->handleRemovedComponentPosition($resource);
        }
        if ($resource instanceof AbstractComponent) {
            $this->orphanedResourceHelper->handleRemovedOrphanedComponent($resource);
        }
    }

    private function resolves(string $iri): bool
    {
        try {
            $this->iriConverter->getResourceFromIri($iri);

            return true;
        } catch (ExceptionInterface) {
            return false;
        }
    }
}
