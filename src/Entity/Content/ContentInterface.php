<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Content\Component\ComponentLocation;

/**
 * Interface ContentInterface
 * @package Silverback\ApiComponentBundle\Entity\Content
 */
interface ContentInterface
{
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
