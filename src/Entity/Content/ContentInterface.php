<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Component\ComponentInterface;

/**
 * Interface ContentInterface
 * @package Silverback\ApiComponentBundle\Entity\Content
 */
interface ContentInterface
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
     * @param ComponentInterface $component
     * @return AbstractContent
     */
    public function addComponent(ComponentInterface $component): AbstractContent;

    /**
     * @param ComponentInterface $component
     * @return AbstractContent
     */
    public function removeComponent(ComponentInterface $component): AbstractContent;
}
