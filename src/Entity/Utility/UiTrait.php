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

namespace Silverback\ApiComponentsBundle\Entity\Utility;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentCollection;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @internal
 */
trait UiTrait
{
    /** @ORM\Column(nullable=true) */
    public ?string $uiComponent = null;

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $uiClassNames = null;

    /**
     * @ORM\ManyToMany(targetEntity=ComponentCollection::class)
     *
     * @var Collection|ComponentCollection[]
     */
    private Collection $componentCollections;

    private function initComponentCollections(): void
    {
        $this->componentCollections = new ArrayCollection();
    }

    /**
     * @return Collection|ComponentCollection[]
     */
    public function getComponentCollections()
    {
        return $this->componentCollections;
    }

    /**
     * @return static
     */
    public function setComponentCollections(iterable $componentCollections)
    {
        $this->componentCollections = new ArrayCollection();
        foreach ($componentCollections as $componentCollection) {
            $this->addComponentCollection($componentCollection);
        }

        return $this;
    }

    /**
     * @return static
     */
    public function addComponentCollection(ComponentCollection $componentCollection)
    {
        if (!$this->componentCollections->contains($componentCollection)) {
            $this->componentCollections->add($componentCollection);
        }

        return $this;
    }

    /**
     * @return static
     */
    public function removeComponentCollection(ComponentCollection $componentCollection)
    {
        if ($this->componentCollections->contains($componentCollection)) {
            $this->componentCollections->remove($componentCollection);
        }

        return $this;
    }
}
