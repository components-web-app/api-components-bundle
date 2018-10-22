<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\Tabs;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\AbstractNavigation;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;

/**
 * Class Tabs
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\Tabs
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class Tabs extends AbstractNavigation
{
    public function __construct()
    {
        parent::__construct();
        $this->addValidComponent(TabsItem::class);
        $this->addComponentGroup(new ComponentGroup());
    }

    public function onDeleteCascade(): bool
    {
        return true;
    }
}
