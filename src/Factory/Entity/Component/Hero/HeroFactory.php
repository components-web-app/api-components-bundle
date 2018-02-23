<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Hero;

use Silverback\ApiComponentBundle\Entity\Component\Hero\Hero;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;

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
        $component->setTitle($this->ops['title']);
        $component->setSubtitle($this->ops['subtitle']);
        $component->setTabs($this->ops['tabs']);
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
                'subtitle' => null,
                'tabs' => null
            ]
        );
    }
}
