<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Page;

abstract class AbstractComponent implements ComponentInterface
{
    /**
     * @var ObjectManager
     */
    protected $manager;

    public function __construct(
        ObjectManager $manager
    )
    {
        $this->manager = $manager;
    }

    public function create($owner, ?array $ops): Component
    {
        $component = self::getComponent();
        $this->setOwner($component, $owner);
        $this->manager->persist($component);
        return $component;
    }

    public function processOps(?array $ops): array
    {
        if (!$ops) {
            $ops = [];
        }
        return array_filter(
            array_merge(self::defaultOps(), $ops),
            function ($key) {
                return in_array($key, array_keys(self::defaultOps()));
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function defaultOps (): array
    {
        return [];
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
