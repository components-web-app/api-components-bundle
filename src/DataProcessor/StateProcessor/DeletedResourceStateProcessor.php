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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\PageDataInterface;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Helper\OrphanedResourceHelper;

/**
 * @implements ProcessorInterface<mixed, mixed>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class DeletedResourceStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private ProcessorInterface $decorated,
        private OrphanedResourceHelper $orphanedResourceHelper,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation instanceof HttpOperation && HttpOperation::METHOD_DELETE === $operation->getMethod()) {
            $this->handleRemoved($data, $operation->getClass());
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    private function handleRemoved(mixed $data, ?string $resourceClass): void
    {
        if ($data instanceof ComponentPosition) {
            $this->orphanedResourceHelper->handleRemovedComponentPosition($data);

            return;
        }

        if ($data instanceof Page || $data instanceof AbstractComponent || $data instanceof Layout) {
            $this->orphanedResourceHelper->handleRemovedRootResource($data);
        }

        if ($data instanceof ComponentGroup) {
            $this->orphanedResourceHelper->handleRemovedComponentGroup($data);
        }

        if ($data instanceof PageDataInterface) {
            $this->orphanedResourceHelper->handleRemovedPageData($data, $resourceClass);
        }

        if ($data instanceof RoutableInterface) {
            $this->orphanedResourceHelper->handleRemovedRoutable($data);
        }
    }
}
