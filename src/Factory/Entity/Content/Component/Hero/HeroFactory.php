<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Hero;

use Silverback\ApiComponentBundle\Entity\Content\Component\Hero\Hero;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class HeroFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Hero
    {
        $component = new Hero();
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
                'title' => 'Untitled',
                'subtitle' => null
            ]
        );
    }
}
