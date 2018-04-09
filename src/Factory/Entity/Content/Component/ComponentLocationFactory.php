<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component;

use Silverback\ApiComponentBundle\Entity\Content\Component\ComponentLocation;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

class ComponentLocationFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): ComponentLocation
    {
        $component = new ComponentLocation();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }

    /**
     * @inheritdoc
     */
    protected static function defaultOps(): array
    {
        return [
            'component' => null,
            'content' => null,
            'dynamicPageClass' => null
        ];
    }
}
