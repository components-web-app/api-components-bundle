<?php

namespace Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Entity\SortableTrait;
use Silverback\ApiComponentBundle\Entity\ValidComponentInterface;
use Silverback\ApiComponentBundle\Entity\ValidComponentTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class ComponentGroup
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class ComponentGroup extends AbstractContent implements ValidComponentInterface, SortableInterface
{
    use SortableTrait;
    use ValidComponentTrait;

    /**
     * @Groups({"default"})
     */
    protected $componentLocations;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Component\AbstractComponent", inversedBy="componentGroups")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @var AbstractComponent
     */
    protected $parent;

    public function __construct(?AbstractComponent $parent = null)
    {
        if ($parent) {
            $this->setParent($parent);
        }
        $this->validComponents = new ArrayCollection;
        parent::__construct();
    }

    /**
     * @return AbstractComponent|null
     */
    public function getParent(): ?AbstractComponent
    {
        return $this->parent;
    }

    /**
     * @param AbstractComponent|null $parent
     * @param bool|null $cascadeValidComponent
     * @param bool|null $sortLast
     */
    public function setParent(?AbstractComponent $parent, ?bool $cascadeValidComponent = null, ?bool $sortLast = true): void
    {
        $this->parent = $parent;
        if ($parent && $cascadeValidComponent !== false) {
            // convert to bool again for $force (null becomes false)
            $this->cascadeValidComponents($parent, (bool) $cascadeValidComponent);
        }
        if (null === $this->sort || $sortLast !== null) {
            $this->setSort($this->calculateSort($sortLast));
        }
    }

    public function hasComponent(AbstractComponent $component)
    {
        foreach ($this->getComponentLocations() as $componentLocation) {
            if ($component === $componentLocation->getComponent()) {
                return true;
            }
        }
        return false;
    }

    public function getSortCollection(): Collection
    {
        return $this->parent ? $this->parent->getComponentGroups() : new ArrayCollection;
    }
}
