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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Entity\Utility\ImagineFiltersInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ImagineFiltersPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    private PropertyMetadataFactoryInterface $decorated;

    public function __construct(PropertyMetadataFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);
        if ('imagineFilters' !== $property || !is_a($resourceClass, ImagineFiltersInterface::class, true)) {
            return $propertyMetadata;
        }

        return $propertyMetadata->withReadable(false);
    }
}
