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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;

/**
 * We must define this as an API resource, otherwise when serializing and the relation is to this class,
 * API Platform does not know that it will be a resource and will make it an object, not an IRI.
 *
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(collectionOperations={}, itemOperations={ "GET" })
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
