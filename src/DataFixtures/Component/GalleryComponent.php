<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Gallery\Gallery;

class GalleryComponent extends AbstractComponent
{
    public static function getComponent(): Component
    {
        return new Gallery();
    }

    public function create($owner, array $ops = null): Component
    {
        /**
         * @var Gallery $component
         */
        $ops = self::processOps($ops);
        $component = parent::create($owner, $ops);
        return $component;
    }
}
