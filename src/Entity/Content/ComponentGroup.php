<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;

/**
 * Class ComponentGroup
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 */
class ComponentGroup extends AbstractContent
{
    /**
     * @var AbstractComponent
     */
    protected $parent;

    /**
     * @return AbstractComponent
     */
    public function getParent(): AbstractComponent
    {
        return $this->parent;
    }

    /**
     * @param AbstractComponent $parent
     */
    public function setParent(AbstractComponent $parent): void
    {
        $this->parent = $parent;
    }
}
