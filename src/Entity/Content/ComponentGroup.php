<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\ValidComponentInterface;
use Silverback\ApiComponentBundle\Entity\ValidComponentTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class ComponentGroup
 * @package Silverback\ApiComponentBundle\Entity\Content\Component
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 * @ORM\Entity()
 */
class ComponentGroup extends AbstractContent implements ValidComponentInterface
{
    use ValidComponentTrait;

    /**
     * @Groups({"default"})
     */
    protected $componentLocations;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent", inversedBy="componentGroups")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @var AbstractComponent
     */
    protected $parent;

    public function __construct()
    {
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
     */
    public function setParent(?AbstractComponent $parent, ?bool $cascadeValidComponent = null): void
    {
        $this->parent = $parent;
        if ($parent && $cascadeValidComponent !== false) {
            // convert to bool again for $force (null becomes false)
            $this->cascadeValidComponents($parent, (bool) $cascadeValidComponent);
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
}
