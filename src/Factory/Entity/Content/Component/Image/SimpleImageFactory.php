<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Image;

use Silverback\ApiComponentBundle\Entity\Content\Component\Image\SimpleImage;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class SimpleImageFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): SimpleImage
    {
        $component = new SimpleImage();
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
            parent::defaultOps(),
            [
                'caption' => null,
                'filePath' => null
            ]
        );
    }
}
