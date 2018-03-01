<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Route;

use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

class RouteFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Route
    {
        $component = new Route();
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
            'route' => null,
            'content' => null,
            'redirect' => null
        ];
    }
}
