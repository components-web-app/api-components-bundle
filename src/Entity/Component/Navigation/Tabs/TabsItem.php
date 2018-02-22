<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigationItem;

/**
 * Class TabsItem
 * @package Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(attributes={"force_eager"=false})
 * @ORM\Entity()
 */
class TabsItem extends AbstractNavigationItem
{
}
