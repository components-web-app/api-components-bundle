<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\ImageComponent;

use Silverback\ApiComponentBundle\Entity\Content\Component\ImageComponent\ImageComponent;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ImageComponentFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): ImageComponent
    {
        $component = new ImageComponent();
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
