<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav\Tabs;

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentBundle\Entity\Component\Nav\AbstractNavItem;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ApiResource()
 */
class Tab extends AbstractNavItem
{
    /**
     * @ORM\ManyToOne(targetEntity="Tabs", inversedBy="items")
     * @var Tabs
     */
    protected $nav;
}
