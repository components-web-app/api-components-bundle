<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav\Tabs;

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentBundle\Entity\Component\Nav\NavItem;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class Tab extends NavItem
{
    /**
     * @ORM\ManyToOne(targetEntity="Tabs", inversedBy="items")
     * @var Tabs
     */
    protected $nav;
}
