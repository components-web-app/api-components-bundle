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

namespace Silverback\ApiComponentsBundle\Entity\Component;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Validator\Constraints as AcbAssert;
use Symfony\Component\Validator\Constraints as Assert;
use Traversable;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @ORM\Entity
 */
class Collection extends AbstractComponent
{
    /**
     * @ORM\Column(nullable=false)
     * @Assert\NotNull(message="The resource iri for a collection component is required")
     * @AcbAssert\ResourceIri()
     */
    private string $resourceIri;

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

    public function getResourceIri(): string
    {
        return $this->resourceIri;
    }

    public function setResourceIri(string $resourceIri): self
    {
        $this->resourceIri = $resourceIri;

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
