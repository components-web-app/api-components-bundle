<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Collection;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;

/**
 * @ORM\Entity()
 * @ApiResource()
 * @author Daniel West <daniel@silverback.is>
 */
class Collection extends AbstractComponent
{
    /**
     * @ORM\Column()
     * @var string
     */
    private $resource;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    private $perPage = 12;

    /**
     * @var array|\Traversable
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
}
