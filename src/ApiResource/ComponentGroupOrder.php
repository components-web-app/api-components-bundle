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
use ApiPlatform\Metadata\Patch;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\ComponentGroupOrderStateProcessor;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\ComponentGroupOrderStateProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    uriTemplate: '/component_groups/{id}/order{._format}',
    operations: [
        new Get(),
        new Patch(processor: ComponentGroupOrderStateProcessor::class),
    ],
    uriVariables: ['id' => new Link(fromClass: self::class, identifiers: ['id'])],
    requirements: ['id' => '.+'],
    normalizationContext: ['groups' => ['ComponentGroupOrder:read'], AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true],
    denormalizationContext: ['groups' => ['ComponentGroupOrder:write']],
    provider: ComponentGroupOrderStateProvider::class,
)]
final class ComponentGroupOrder
{
    /**
     * @param array<string>             $componentPositions
     * @param \ArrayObject<string, int> $sortValues
     */
    public function __construct(
        #[ApiProperty(readable: false, writable: false, identifier: true)]
        public string $id,
        #[Groups(['ComponentGroupOrder:read'])]
        public string $componentGroup,
        #[Groups(['ComponentGroupOrder:read', 'ComponentGroupOrder:write'])]
        #[Assert\All([new Assert\Type('string')])]
        public array $componentPositions,
        #[Groups(['ComponentGroupOrder:read'])]
        public \ArrayObject $sortValues,
    ) {
    }
}
