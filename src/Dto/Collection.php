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

namespace Silverback\ApiComponentBundle\Dto;

use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Entity\Component\Collection as CollectionResource;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Traversable;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class Collection
{
    private CollectionResource $resource;

    /**
     * @SerializedName("collection")
     *
     * @var array|Traversable
     */
    private $collection;

    private ?ArrayCollection $endpoints = null;

    public function __construct(CollectionResource $parentObj)
    {
        $this->resource = $parentObj;
    }

    public function getResource(): CollectionResource
    {
        return $this->resource;
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
