<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\ApiPlatform\Api;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class IriConverter implements IriConverterInterface
{
    public function __construct(private IriConverterInterface $decorated, private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory)
    {
    }

    public function getResourceFromIri(string $iri, array $context = [], ?Operation $operation = null): object
    {
        return $this->decorated->getResourceFromIri($iri, $context, $operation);
    }

    private function getUserGetOperation(?Operation $operation = null): Operation
    {
        // we do not want to return the /me IRI if a Get endpoint is configured. The IRI should be canonical to the user's ID etc.

        // get the API metadata of the class
        // find the Get operation - ApiPlatform\Metadata\Get
        // use this uriTemplate instead, overwrite $operation and the operation in context
        $resourceIterator = $this->resourceMetadataCollectionFactory->create($operation->getClass())->getIterator();
        while ($resourceIterator->valid()) {
            $current = $resourceIterator->current();
            /**
             * @var Operations $resourceOperations
             */
            $resourceOperations = $current->getOperations();
            $operationIterator = $resourceOperations->getIterator();
            while ($operationIterator->valid()) {
                $checkOperation = $operationIterator->current();
                if ($checkOperation instanceof Get) {
                    return $checkOperation;
                }
                $operationIterator->next();
            }
            $resourceIterator->next();
        }

        return $operation;
    }

    // We want relations when they are found, to use the IRI with the path
    public function getIriFromResource($resource, int $referenceType = UrlGeneratorInterface::ABS_PATH, ?Operation $operation = null, array $context = []): ?string
    {
        if ('me' === $operation?->getName()) {
            $checkOperation = $this->getUserGetOperation($operation);
            $operation = $checkOperation;
            $context['operation'] = $checkOperation;
        }

        $originalIri = $this->decorated->getIriFromResource($resource, $referenceType, $operation, $context);

        if (!$resource instanceof Route || !($path = $resource->getPath())) {
            return $originalIri;
        }

        $id = $resource->getId();
        if (!$id) {
            // id may not exist on object anymore. Deleting a page data resource with the route on will cascade,
            // then mercure will want to publish the change with the IRI
            $parts = explode('/', $originalIri);
            array_pop($parts);
            $parts[] = $path;

            return implode('/', $parts);
        }

        return str_replace($id->toString(), $path, $originalIri);
    }
}
