<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Content;

use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Component\ComponentLocation;
use Silverback\ApiComponentBundle\Entity\TimestampedEntityInterface;

/**
 * Interface ContentInterface
 * @package Silverback\ApiComponentBundle\Entity\Content
 */
interface ContentInterface extends TimestampedEntityInterface
{
    /**
     * @return Collection|ComponentLocation[]
     */
    public function getComponentLocations(): Collection;

    /**
     * @param ComponentLocation $componentLocation
     * @return AbstractContent
     */
    public function addComponentLocation(ComponentLocation $componentLocation): AbstractContent;

    /**
     * @param ComponentLocation $componentLocation
     * @return AbstractContent
     */
    public function removeComponentLocation(ComponentLocation $componentLocation): AbstractContent;
}
