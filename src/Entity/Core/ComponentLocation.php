<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Core;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ApiResource(attributes={"order"={"sort"="ASC"}})
 * @ORM\Entity
 */
class ComponentLocation
{
    use IdTrait;
    use TimestampedTrait;

    /** @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\ComponentGroup", inversedBy="componentLocations") */
    public ComponentGroup $componentGroup;

    /**
     * @ApiProperty(writable=false)
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\AbstractComponent", inversedBy="componentLocations")
     */
    public AbstractComponent $component;

    /**
     * @ORM\Column(type="integer")
     */
    public ?int $sort = 0;

    public function __construct()
    {
        $this->setId();
    }

    /**
     * @return Collection|AbstractComponent[]|null
     */
    public function getSortCollection(): ?Collection
    {
        return $this->componentGroup->componentLocations;
    }
}
