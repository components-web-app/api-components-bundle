<?php

namespace Silverback\ApiComponentBundle\Factory\Fixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Hero\Hero;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class HeroFactory extends AbstractComponentFactory
{
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

    /**
     * @inheritdoc
     */
    public function create(?array $ops = null, ?AbstractContent $owner = null): Hero
    {
        $component = new Hero();
        $this->init($component, $ops);
        $component->setTitle($this->ops['title']);
        $component->setSubtitle($this->ops['subtitle']);
        $component->setTabs($this->ops['tabs']);
        return $component;
    }
}
