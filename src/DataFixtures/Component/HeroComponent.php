<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Hero;

class HeroComponent extends AbstractComponent
{
    public static function getComponent(): Component
    {
        return new Hero();
    }

    public static function defaultOps(): array
    {
        return array_merge(parent::defaultOps(), [
            'title' => 'No Title Set',
            'subtitle' => null
        ]);
    }

    public function create($owner, array $ops = null): Component
    {
        /**
         * @var Hero $component
         */
        $ops = self::processOps($ops);
        $component = parent::create($owner, $ops);
        $component->setTitle($ops['title']);
        $component->setSubtitle($ops['subtitle']);
        return $component;
    }
}
