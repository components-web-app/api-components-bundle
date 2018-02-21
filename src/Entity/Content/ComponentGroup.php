<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\ValidComponentInterface;
use Silverback\ApiComponentBundle\Entity\ValidComponentTrait;

/**
 * Class ComponentGroup
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 */
class ComponentGroup extends AbstractContent implements ValidComponentInterface
{
    use ValidComponentTrait;

    /**
     * @var Component
     */
    protected $parent;

    public function __construct()
    {
        $this->validComponents = new ArrayCollection;
        parent::__construct();
    }


    /**
     * @return Component
     */
    public function getParent(): Component
    {
        return $this->parent;
    }

    /**
     * @param Component $parent
     * @param bool|null $cascadeValidComponent
     */
    public function setParent(Component $parent, ?bool $cascadeValidComponent = null): void
    {
        $this->parent = $parent;
        if ($cascadeValidComponent !== false) {
            // convert to bool again for $force (null becomes false)
            $this->cascadeValidComponents($parent, (bool) $cascadeValidComponent);
        }
    }
}
