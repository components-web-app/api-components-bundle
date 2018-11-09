<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component;

use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\Component\ComponentLocation;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

abstract class AbstractComponentFactory extends AbstractFactory
{
    public const COMPONENT_OPS = [
        'className' => null,
        'parentComponent' => null,
        'parentContent' => null,
        'componentGroup' => null,
        'dynamicPageClass' => null,
        'componentName' => null
    ];

    /**
     * @param AbstractComponent $component
     * @param array|null $ops
     */
    protected function init($component, ?array $ops = null): void
    {
        parent::init($component, $ops);
        if (
            $this->ops['parentContent'] &&
            !$component->hasParentContent($this->ops['parentContent'])
        ) {
            $location = new ComponentLocation($this->ops['parentContent'], $component);
            $component->addLocation($location);
            $this->manager->persist($location);
        }
        if (
        $this->ops['dynamicPageClass']
        ) {
            $location = new ComponentLocation(null, $component);
            $location->setDynamicPageClass($this->ops['dynamicPageClass']);
            $component->addLocation($location);
            $this->manager->persist($location);
        }
    }

    protected static function getIgnoreOps(): array
    {
        return [
            'parentContent',
            'dynamicPageClass'
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defaultOps(): array
    {
        return self::COMPONENT_OPS;
    }
}
