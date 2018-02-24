<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Navigation\Menu;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigation;

/**
 * Class Menu
 * @package Silverback\ApiComponentBundle\Entity\Component\Navigation\Menu
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 * @ORM\Entity()
 */
class Menu extends AbstractNavigation
{
}
