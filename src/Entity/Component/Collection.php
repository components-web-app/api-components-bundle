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

namespace Silverback\ApiComponentBundle\Entity\Component;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedTrait;
use Symfony\Component\Validator\Constraints as Assert;
use Traversable;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ORM\Entity
 */
class Collection extends AbstractComponent
{
    use TimestampedTrait;

    /**
     * @ORM\Column(nullable=false)
     * @Assert\NotNull(message="The resource class for a collection component is required")
     */
    private string $resourceClass;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $perPage;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $defaultQueryParameters;

    /**
     * @ApiProperty(writable=false)
     */
    private array $collection = [];

    /**
     * @ApiProperty(writable=false)
     */
    private ?ArrayCollection $endpoints = null;

    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    public function setResourceClass(string $resourceClass): self
    {
        $this->resourceClass = $resourceClass;

        return $this;
    }

    public function getPerPage(): ?int
    {
        return $this->perPage;
    }

    public function setPerPage(?int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function getDefaultQueryParameters(): ?array
    {
        return $this->defaultQueryParameters;
    }

    public function setDefaultQueryParameters(?array $defaultQueryParameters): self
    {
        $this->defaultQueryParameters = $defaultQueryParameters;

        return $this;
    }

    public function getCollection()
    {
        return $this->collection;
    }

    public function setCollection($collection): self
    {
        if (!$collection instanceof Traversable && !\is_array($collection)) {
            return $this;
        }
        $this->collection = $collection;

        return $this;
    }

    public function getEndpoints(): ?ArrayCollection
    {
        return $this->endpoints;
    }

    public function setEndpoints(ArrayCollection $endpoints): self
    {
        $this->endpoints = $endpoints;

        return $this;
    }

    public function addEndpoint(string $method, string $route): self
    {
        if (!$this->endpoints) {
            $this->endpoints = new ArrayCollection();
        }
        $this->endpoints->set($method, $route);

        return $this;
    }
}
