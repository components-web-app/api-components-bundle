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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractComponent implements ComponentInterface
{
    use IdTrait;
    use UiTrait;

    /**
     * @var Collection|ComponentPosition[]
     */
    private Collection $componentPositions;

    public function __construct()
    {
        $this->initComponentCollections();
        $this->componentPositions = new ArrayCollection();
    }

    public function isPositionRestricted(): bool
    {
        return false;
    }

    public function getComponentPositions()
    {
        return $this->componentPositions;
    }

    public function setComponentPositions(iterable $componentPositions): self
    {
        $this->componentPositions = new ArrayCollection();
        foreach ($componentPositions as $componentPosition) {
            $this->addComponentPosition($componentPosition);
        }

        return $this;
    }

    public function addComponentPosition(ComponentPosition $componentPosition): self
    {
        $componentPosition->setComponent($this);
        $this->componentPositions->add($componentPosition);

        return $this;
    }
}
