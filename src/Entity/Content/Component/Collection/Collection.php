<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Collection;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ApiResource()
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
     */
    public function setResource(string $resource): void
    {
        $this->resource = $resource;
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
     */
    public function setPerPage(int $perPage): void
    {
        $this->perPage = $perPage;
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
     */
    public function setTitle(?string $title): void
    {
        $this->title = $title;
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
    public function setCollection($collection): void
    {
        $this->collection = $collection;
    }

    /**
     * @ORM\PostLoad()
     */
    public function postLoad() {

    }
}
