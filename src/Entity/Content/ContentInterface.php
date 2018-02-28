<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;

/**
 * Interface ContentInterface
 * @package Silverback\ApiComponentBundle\Entity\Content
 */
interface ContentInterface extends RouteAwareInterface
{
    /**
     * ContentInterface constructor.
     */
    public function __construct();

    /**
     * @return Collection
     */
    public function getComponents(): Collection;

    /**
     * @param ComponentLocation $component
     * @return AbstractContent
     */
    public function addComponent(ComponentLocation $component): AbstractContent;

    /**
     * @param ComponentLocation $component
     * @return AbstractContent
     */
    public function removeComponent(ComponentLocation $component): AbstractContent;
}
