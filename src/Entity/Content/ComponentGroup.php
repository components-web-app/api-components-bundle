<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\ValidComponentInterface;

/**
 * Class ComponentGroup
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 */
class ComponentGroup extends AbstractContent implements ValidComponentInterface
{
    use Silverback\ApiComponentBundle\Entity\ValidComponentTrait;

    /**
     * @var AbstractComponent
     */
    protected $parent;

    public function __construct()
    {
        $this->validComponents = new ArrayCollection;
        parent::__construct();
    }


    /**
     * @return AbstractComponent
     */
    public function getParent(): AbstractComponent
    {
        return $this->parent;
    }

    /**
     * @param AbstractComponent $parent
     * @param bool|null $cascadeValidComponent
     */
    public function setParent(AbstractComponent $parent, ?bool $cascadeValidComponent = null): void
    {
        $this->parent = $parent;
        if ($cascadeValidComponent !== false) {
            // convert to bool again for $force (null becomes false)
            $this->cascadeValidComponents($parent, (bool) $cascadeValidComponent);
        }
    }
}
