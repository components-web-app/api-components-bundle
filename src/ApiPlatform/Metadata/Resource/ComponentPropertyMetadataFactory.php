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

namespace Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource;

use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;

/**
 * We should allow componentPositions to be writable. API Platform will not do this automatically based on
 * AbstractComponent xml definitions or groups as we define them using our decorators.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    private PropertyMetadataFactoryInterface $decorated;

    public function __construct(PropertyMetadataFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function create(string $resourceClass, string $property, array $options = []): PropertyMetadata
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);
        if ('componentPositions' === $property && !is_a($resourceClass, AbstractComponent::class, true)) {
            return $propertyMetadata;
        }

        return $propertyMetadata->withWritableLink(true);
    }
}
