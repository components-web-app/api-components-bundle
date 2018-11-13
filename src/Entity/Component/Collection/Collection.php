<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Collection;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Symfony\Component\Serializer\Annotation\Groups;

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
     * @ORM\Column(type="integer")
     * @Groups({"component", "content"})
     * @var int
     */
    private $perPage = 12;

    /**
     * @ORM\Column(nullable=true)
     * @Groups({"component", "content"})
     * @var string|null
     */
    private $title;

    /**
     * @var array|\Traversable
     * @Groups({"component_read", "content_read"})
     */
    private $collection;

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
    public function setResource(string $resource): self
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * @return int
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * @param int $perPage
     * @return Collection
     */
    public function setPerPage(int $perPage): self
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
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return array|\Traversable
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * @param array|\Traversable $collection
     */
    public function setCollection($collection): self
    {
        $this->collection = $collection;
        return $this;
    }

    /**
     * @ORM\PostLoad()
     */
    public function postLoad()
    {
    }
}
