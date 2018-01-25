<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\News\News;

class NewsComponent extends AbstractComponent
{
    public static function getComponent(): Component
    {
        return new News();
    }

    public static function defaultOps(): array
    {
        return [];
    }

    public function create($owner, array $ops = null): Component
    {
        /**
         * @var News $component
         */
        $ops = self::processOps($ops);
        $component = parent::create($owner, $ops);
        return $component;
    }
}
