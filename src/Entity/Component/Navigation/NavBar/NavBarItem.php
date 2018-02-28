<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Navigation\NavBar;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Navigation\AbstractNavigationItem;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 * @ORM\Entity()
 */
class NavBarItem extends AbstractNavigationItem
{
}
