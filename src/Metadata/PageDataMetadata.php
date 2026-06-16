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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\PageDataMetadataStateProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * @internal
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource(
    normalizationContext: ['jsonld_embed_context' => true, 'groups' => ['PageDataMetadata:cwa_resource:read']],
    operations: [
        new Get(provider: PageDataMetadataStateProvider::class),
        new GetCollection(provider: PageDataMetadataStateProvider::class),
    ]
)]
class PageDataMetadata
{
    #[ApiProperty(readable: true, writable: false, identifier: true)]
    #[Groups(['AbstractPageData:cwa_resource:read', 'PageDataMetadata:cwa_resource:read'])]
    private string $resourceClass;

    /**
     * @var Collection|PageDataPropertyMetadata[]
     */
    #[ApiProperty(writable: false)]
    #[Groups(['AbstractPageData:cwa_resource:read', 'PageDataMetadata:cwa_resource:read'])]
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

    public function findPropertiesByComponentShortName(string $componentClass): Collection
    {
        return $this->properties->filter(static function (PageDataPropertyMetadata $propertyMetadata) use ($componentClass) {
            return $propertyMetadata->getComponentShortName() === $componentClass;
        });
    }
}
