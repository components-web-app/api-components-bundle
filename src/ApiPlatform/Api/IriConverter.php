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

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class IriConverter implements IriConverterInterface
{
    public function __construct(private IriConverterInterface $decorated)
    {
    }

    public function getResourceFromIri(string $iri, array $context = [], ?Operation $operation = null): object
    {
        return $this->decorated->getResourceFromIri($iri, $context, $operation);
    }

    // We want relations when they are found, to use the IRI with the path
    public function getIriFromResource($resource, int $referenceType = UrlGeneratorInterface::ABS_PATH, ?Operation $operation = null, array $context = []): ?string
    {
        $originalIri = $this->decorated->getIriFromResource($resource, $referenceType, $operation, $context);

        if (!$resource instanceof Route || !($path = $resource->getPath())) {
            return $originalIri;
        }

        $id = $resource->getId();
        if (!$id) {
            // id may not exist on object anymore. Deleting a page data resource with the route on will cascade,
            //then mercure will want to publish the change with the IRI
            $parts = explode('/', $originalIri);
            array_pop($parts);
            $parts[] = $path;
            return join('/', $parts);
        }
        return str_replace($id->toString(), $path, $originalIri);
    }
}
