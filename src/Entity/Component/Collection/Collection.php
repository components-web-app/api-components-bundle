<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Collection;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Symfony\Component\Serializer\Annotation\Groups;
use Traversable;

/**
 * @ORM\Entity()
 * @author Daniel West <daniel@silverback.is>
 */
class Collection extends AbstractComponent
{
    /**
     * @ORM\Column()
     * @Groups({"component", "content"})
     * @var string
     */
    private $resource;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"component", "content"})
     * @var int|null
     */
    private $perPage;

    /**
     * @ORM\Column(nullable=true)
     * @Groups({"component", "content"})
     * @var string|null
     */
    private $title;

    /**
     * @var array|Traversable
     * @Groups({"component_read", "content_read"})
     */
    private $collection;

    /**
     * @var ArrayCollection
     * @Groups({"component_read", "content_read"})
     */
    private $collectionRoutes;

    /**
     * @ORM\Column(nullable=true)
     * @Groups({"component", "content"})
     * @var string|null
     */
    private $defaultQueryString;

    /**
     * Collection constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->collectionRoutes = new ArrayCollection;
    }

    /**
     * @return string
     */
    public function getResource(): string
    {
        return $this->resource;
    }

    /**
     * @param string $resource
     * @return Collection
     */
    public function setResource(string $resource): Collection
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * @return null|int
     */
    public function getPerPage(): ?int
    {
        return $this->perPage;
    }

    /**
     * @param null|int $perPage
     * @return Collection
     */
    public function setPerPage(?int $perPage): Collection
    {
        $this->perPage = $perPage;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param null|string $title
     * @return Collection
     */
    public function setTitle(?string $title): Collection
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return array|Traversable
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * @param array|Traversable $collection
     * @return Collection
     */
    public function setCollection($collection): Collection
    {
        $this->collection = $collection;
        return $this;
    }

    /**
     * @return ArrayCollection|null
     */
    public function getCollectionRoutes(): ?ArrayCollection
    {
        return $this->collectionRoutes;
    }

    /**
     * @param string $method
     * @param string $route
     * @return static
     */
    public function addCollectionRoute(string $method, string $route)
    {
        if (!$this->collectionRoutes) {
            $this->collectionRoutes = new ArrayCollection;
        }
        $this->collectionRoutes->set($method, $route);
        return $this;
    }

    /**
     * @return null|string
     */
    public function getDefaultQueryString(): ?string
    {
        return $this->defaultQueryString;
    }

    /**
     * @param null|string $defaultQueryString
     * @return Collection
     */
    public function setDefaultQueryString(?string $defaultQueryString): Collection
    {
        $this->defaultQueryString = $defaultQueryString;
        return $this;
    }
}
