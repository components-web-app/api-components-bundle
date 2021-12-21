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

namespace Silverback\ApiComponentsBundle\Metadata;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 *
 * @author Daniel West <daniel@silverback.is>
 */
class PageDataMetadata
{
    /**
     * @Groups({"AbstractPageData:cwa_resource:read"})
     */
    private string $resourceClass;

    /**
     * @var Collection|PageDataPropertyMetadata[]
     * @Groups({"AbstractPageData:cwa_resource:read"})
     */
    private Collection $properties;

    public function __construct(string $resourceClass)
    {
        $this->resourceClass = $resourceClass;
        $this->properties = new ArrayCollection();
    }

    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function setProperties(iterable $properties): self
    {
        $this->properties = new ArrayCollection();

        return $this->addProperties($properties);
    }

    public function addProperties(iterable $properties): self
    {
        foreach ($properties as $property) {
            $this->addProperty($property);
        }

        return $this;
    }

    public function addProperty(PageDataPropertyMetadata $propertyMetadata): self
    {
        if ($this->properties->contains($propertyMetadata)) {
            return $this;
        }
        $this->properties->add($propertyMetadata);

        return $this;
    }

    public function removeProperty(PageDataPropertyMetadata $propertyMetadata): self
    {
        $this->properties->removeElement($propertyMetadata);

        return $this;
    }
}
