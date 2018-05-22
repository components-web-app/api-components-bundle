<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content;

use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

class ComponentGroupFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): ComponentGroup
    {
        $component = new ComponentGroup();
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
            'parent' => null,
            'sort' => null
        ];
    }
}
