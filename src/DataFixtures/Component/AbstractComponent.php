<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\AbstractFixture;
use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Page;

abstract class AbstractComponent extends AbstractFixture implements ComponentInterface
{
    /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        $this->entity = $this->getComponent();
    }

    public function create($owner, ?array $ops = null): Component
    {
        $ops = $this->processOps($ops);
        $component = $this->getComponent();
        $this->setOwner($component, $owner);
        $component->setClassName($ops['className']);
        $this->manager->persist($component);
        return $component;
    }

    public function processOps(?array $ops): array
    {
        if (!$ops) {
            $ops = [];
        }
        return array_filter(
            array_merge(static::defaultOps(), $ops),
            function ($key) {
                return in_array($key, array_keys(static::defaultOps()));
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function defaultOps (): array
    {
        return [ 'className' => null ];
    }

    private function setOwner(Component &$component, $entity) {
        switch (true)
        {
            case $entity instanceof ComponentGroup:
                $component->setGroup($entity);
                break;

            case $entity instanceof Page:
                $component->setPage($entity);
                break;

            default:
                throw new \InvalidArgumentException(vsprintf('Owner entity of a component must be %s or %s', [
                    Component::class,
                    ComponentGroup::class
                ]));
                break;
        }
    }
}
