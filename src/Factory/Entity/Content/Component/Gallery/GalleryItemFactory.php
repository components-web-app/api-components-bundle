<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Gallery;

use Silverback\ApiComponentBundle\Entity\Content\Component\Gallery\GalleryItem;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class GalleryItemFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): GalleryItem
    {
        $component = new GalleryItem();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }

    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            AbstractFactory::COMPONENT_CLASSES,
            [
                'title' => 'Untitled',
                'caption' => null,
                'filePath' => null
            ]
        );
    }
}
