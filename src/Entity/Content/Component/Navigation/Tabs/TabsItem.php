<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\Tabs;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\AbstractNavigationItem;

/**
 * Class TabsItem
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\Tabs
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(shortName="Component/TabsItem")
 * @ORM\Entity()
 */
class TabsItem extends AbstractNavigationItem
{
}
