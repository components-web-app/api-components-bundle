<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component;

use Silverback\ApiComponentBundle\Entity\Component\ComponentLocation;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

class ComponentLocationFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): \Silverback\ApiComponentBundle\Entity\Component\ComponentLocation
    {
        $component = new \Silverback\ApiComponentBundle\Entity\Component\ComponentLocation();
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
