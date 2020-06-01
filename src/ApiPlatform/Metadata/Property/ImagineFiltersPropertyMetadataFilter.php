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

namespace Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Property;

use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use Silverback\ApiComponentsBundle\Entity\Utility\ImagineFiltersInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ImagineFiltersPropertyMetadataFilter implements PropertyMetadataFactoryInterface
{
    private PropertyMetadataFactoryInterface $decorated;

    public function __construct(PropertyMetadataFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function create(string $resourceClass, string $property, array $options = []): PropertyMetadata
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);
        if ('imagineFilters' !== $property || !class_implements($resourceClass, ImagineFiltersInterface::class)) {
            return $propertyMetadata;
        }

        return $propertyMetadata->withReadable(false);
    }
}
