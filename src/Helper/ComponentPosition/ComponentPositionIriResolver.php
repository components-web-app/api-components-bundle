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

use ApiPlatform\Metadata\Exception\ExceptionInterface;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;

final class ComponentPositionIriResolver
{
    public function __construct(private readonly IriConverterInterface $iriConverter)
    {
    }

    public function resolve(?string $iri): ?ComponentPosition
    {
        if (null === $iri || '' === $iri) {
            return null;
        }

        try {
            $resource = $this->iriConverter->getResourceFromIri($iri);
        } catch (ExceptionInterface) {
            return null;
        }

        return $resource instanceof ComponentPosition ? $resource : null;
    }

    public function isMissing(string $iri): bool
    {
        try {
            $this->iriConverter->getResourceFromIri($iri);
        } catch (ItemNotFoundException) {
            return true;
        } catch (ExceptionInterface) {
        }

        return false;
    }
}
