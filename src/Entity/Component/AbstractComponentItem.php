<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractComponentItem
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 *
 * @ORM\Table(name="component_item")
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({
 *     "gallery_item" = "Silverback\ApiComponentBundle\Entity\Component\Gallery\GalleryItem"
 * })
 */
abstract class AbstractComponentItem implements SortableInterface
{
    use SortableTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

    /**
     * @var AbstractComponent
     */
    public $parent;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"component"})
     * @var int|null
     */
    protected $className;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /*
    public function getParent(): AbstractComponent
    {
        return $this->parent;
    }
    */

    public function setParent(AbstractComponent $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return int|null
     */
    public function getClassName(): ?int
    {
        return $this->className;
    }

    /**
     * @param int|null $className
     */
    public function setClassName(?int $className): void
    {
        $this->className = $className;
    }
}
