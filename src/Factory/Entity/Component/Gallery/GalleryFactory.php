<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Gallery;

use Silverback\ApiComponentBundle\Entity\Component\Gallery\Gallery;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class GalleryFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Gallery
    {
        $component = new Gallery();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }
}
