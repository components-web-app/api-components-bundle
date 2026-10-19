<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Metadata;

use Silverback\ApiComponentsBundle\Helper\ComponentUsage\ComponentUsageLocations;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * @phpstan-import-type PresentedLocation from ComponentUsageLocations
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentUsageMetadata
{
    /**
     * @param list<PresentedLocation>|null $locations
     */
    public function __construct(
        #[Groups(['AbstractComponent:cwa_resource:read'])]
        private readonly int $positionCount,
        #[Groups(['AbstractComponent:cwa_resource:read'])]
        private readonly int $pageDataCount,
        #[Groups(['AbstractComponent:cwa_resource:read'])]
        #[Context(normalizationContext: ['api_sub_level' => true])]
        private readonly ?array $locations = null,
    ) {
    }

    public function getPositionCount(): int
    {
        return $this->positionCount;
    }

    public function getPageDataCount(): int
    {
        return $this->pageDataCount;
    }

    #[Groups(['AbstractComponent:cwa_resource:read'])]
    public function getTotal(): int
    {
        return $this->positionCount + $this->pageDataCount;
    }

    /**
     * @return list<PresentedLocation>|null
     */
    public function getLocations(): ?array
    {
        return $this->locations;
    }
}
