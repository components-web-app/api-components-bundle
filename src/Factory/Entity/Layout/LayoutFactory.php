<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Layout;

use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

class LayoutFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Layout
    {
        $component = new Layout();
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
            'default' => null,
            'navBar' => null
        ];
    }
}
