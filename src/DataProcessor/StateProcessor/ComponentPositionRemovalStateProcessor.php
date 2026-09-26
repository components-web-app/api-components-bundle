<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

/**
 * @implements ProcessorInterface<mixed, mixed>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class ComponentPositionRemovalStateProcessor implements ProcessorInterface
{
    use ClassMetadataTrait;

    private readonly PublishableAttributeReader $publishableAttributeReader;

    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private readonly ProcessorInterface $decorated,
        ManagerRegistry $registry,
        PublishableStatusChecker $publishableStatusChecker,
    ) {
        $this->initRegistry($registry);
        $this->publishableAttributeReader = $publishableStatusChecker->getAttributeReader();
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof AbstractComponent && $operation instanceof HttpOperation && HttpOperation::METHOD_DELETE === $operation->getMethod()) {
            $this->removeEmptyPositions($data, $operation->getClass() ?? $data::class);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    private function removeEmptyPositions(AbstractComponent $component, string $resourceClass): void
    {
        $manager = $this->registry->getManagerForClass(ComponentPosition::class);
        if (!$manager) {
            return;
        }

        $positions = $component->getComponentPositions();
        if ($this->publishableAttributeReader->isConfigured($resourceClass)) {
            $configuration = $this->publishableAttributeReader->getConfiguration($resourceClass);
            $draftResource = $this->getClassMetadata($resourceClass)->getFieldValue($component, $configuration->reverseAssociationName);
            if ($draftResource) {
                foreach ($positions as $position) {
                    $position->component = $draftResource;
                }

                return;
            }
        }

        foreach ($positions as $position) {
            if (!$position->pageDataProperty) {
                $manager->remove($position);
            }
        }
    }
}
