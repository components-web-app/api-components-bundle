<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav\Tabs;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigationItem;

class TabItem extends AbstractNavigationItem
{
    /**
     * @ORM\ManyToOne(targetEntity="Tabs", inversedBy="items")
     * @var Tabs
     */
    protected $nav;
}
