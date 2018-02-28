<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Gallery;

use Silverback\ApiComponentBundle\Entity\Component\Gallery\GalleryItem;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class GalleryItemFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): GalleryItem
    {
        $component = new GalleryItem();
        $this->init($component, $ops);
        $component->setTitle($this->ops['title']);
        $component->setCaption($this->ops['caption']);
        $component->setFilePath($this->ops['filePath']);
        $this->validate($component);
        return $component;
    }

    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            parent::defaultOps(),
            [
                'title' => 'Untitled',
                'caption' => null,
                'filePath' => null
            ]
        );
    }
}
