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

namespace Silverback\ApiComponentsBundle\Entity\Core;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ApiResource(attributes={"order"={"sort"="ASC"}})
 */
class ComponentLocation
{
    use IdTrait;
    use TimestampedTrait;

    public ComponentGroup $componentGroup;

    /**
     * @ApiProperty(writable=false)
     */
    public AbstractComponent $component;

    public ?int $sort = 0;

    /**
     * @return Collection|AbstractComponent[]|null
     */
    public function getSortCollection(): ?Collection
    {
        return $this->componentGroup->componentLocations;
    }
}
