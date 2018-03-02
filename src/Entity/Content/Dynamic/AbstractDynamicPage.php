<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Dynamic;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Content\Page;

abstract class AbstractDynamicPage extends Page
{
    public function getComponentLocations(): Collection
    {
        return new ArrayCollection;
    }
}
