<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Gallery;

use Silverback\ApiComponentBundle\Entity\Content\Component\Gallery\Gallery;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

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
