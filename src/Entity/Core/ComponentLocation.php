<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentBundle\Entity\Utility\SortableTrait;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedInterface;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource
 * @ORM\Entity
 */
class ComponentLocation implements TimestampedInterface
{
    use IdTrait;
    use SortableTrait;
    use TimestampedTrait;

    /** @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\ComponentGroup", inversedBy="componentLocations") */
    public ComponentGroup $componentGroup;

    /** @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\AbstractComponent", inversedBy="componentLocations") */
    public AbstractComponent $component;

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
