<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Dynamic;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\PageTrait;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareTrait;
use Symfony\Component\Serializer\Annotation\Groups;

abstract class AbstractDynamicPage extends AbstractContent implements RouteAwareInterface
{
    use RouteAwareTrait;
    use PageTrait;

    public function __construct()
    {
        parent::__construct();
        $this->routes = new ArrayCollection;
    }

    /** @Groups({"dynamic_content"}) */
    public function getComponentLocations(): Collection
    {
        return new ArrayCollection;
    }
}
