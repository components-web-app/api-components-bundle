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

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentsBundle\Validator\Constraints as AcbAssert;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ApiResource(attributes={"order"={"sortValue"="ASC"}}, normalizationContext={"groups"={"ComponentPosition:read"}}, denormalizationContext={"groups"={"ComponentPosition:write"}})
 * @AcbAssert\ComponentPosition
 */
class ComponentPosition
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @Assert\NotNull()
     * @Groups({"ComponentPosition:read", "ComponentPosition:write"})
     */
    public ?ComponentCollection $componentCollection = null;

    /**
     * @Assert\NotNull()
     * @Groups({"ComponentPosition:read", "ComponentPosition:write"})
     */
    public ?AbstractComponent $component = null;

    /**
     * @Assert\NotNull()
     * @Groups({"ComponentPosition:read", "ComponentPosition:write"})
     */
    public int $sortValue = 0;

    /**
     * @return ComponentPosition[]|Collection|null
     */
    public function getSortCollection(): ?Collection
    {
        return $this->componentCollection->componentPositions;
    }

    public function setComponentCollection(ComponentCollection $componentCollection): self
    {
        $this->componentCollection = $componentCollection;

        return $this;
    }

    public function setComponent(AbstractComponent $component): self
    {
        $this->component = $component;

        return $this;
    }

    public function setSortValue(int $sortValue): self
    {
        $this->sortValue = $sortValue;

        return $this;
    }
}
