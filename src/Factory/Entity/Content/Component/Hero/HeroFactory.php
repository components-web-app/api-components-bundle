<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Hero;

use Silverback\ApiComponentBundle\Entity\Content\Component\Hero\Hero;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class HeroFactory extends AbstractFactory
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
            AbstractFactory::COMPONENT_CLASSES,
            [
                'title' => 'Untitled',
                'subtitle' => null,
                'tabs' => null
            ]
        );
    }
}
