<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\NavBar;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\AbstractNavigationItem;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(attributes={"routePrefix"="/component"})
 * @ORM\Entity()
 */
class NavBarItem extends AbstractNavigationItem
{
}
