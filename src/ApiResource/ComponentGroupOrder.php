<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\ComponentGroupOrderStateProcessor;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\ComponentGroupOrderStateProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    uriTemplate: '/component_groups/{id}/order{._format}',
    operations: [
        new Get(),
        new Put(input: ComponentGroupOrderInput::class, processor: ComponentGroupOrderStateProcessor::class),
    ],
    uriVariables: ['id' => new Link(fromClass: self::class, identifiers: ['id'])],
    requirements: ['id' => '.+'],
    normalizationContext: ['groups' => ['ComponentGroupOrder:read']],
    denormalizationContext: ['groups' => ['ComponentGroupOrder:write']],
    provider: ComponentGroupOrderStateProvider::class,
)]
final class ComponentGroupOrder
{
    /**
     * @param array<string, int> $sortValues
     */
    public function __construct(
        #[ApiProperty(readable: false, writable: false, identifier: true)]
        public string $id,
        #[Groups(['ComponentGroupOrder:read'])]
        public ComponentGroup $componentGroup,
        #[Groups(['ComponentGroupOrder:read'])]
        public array $sortValues = [],
    ) {
    }
}
