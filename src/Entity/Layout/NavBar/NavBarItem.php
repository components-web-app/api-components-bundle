<?php

namespace Silverback\ApiComponentBundle\Entity\Layout\NavBar;

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigationItem;

/**
 * Class NavBarItem
 * @package Silverback\ApiComponentBundle\Entity\Layout\NavBar
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(attributes={"force_eager"=false})
 */
class NavBarItem extends AbstractNavigationItem
{}
