<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\Nav\AbstractNav;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ComponentGroup
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ApiResource()
 */
class ComponentGroup extends AbstractContent
{
    /**
     * @ORM\ManyToOne(targetEntity="\Silverback\ApiComponentBundle\Entity\Component\AbstractComponent", inversedBy="childGroups")
     * @Assert\Type({"\Silverback\ApiComponentBundle\Entity\Component\Nav\AbstractNav"})
     * @var AbstractNav
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
