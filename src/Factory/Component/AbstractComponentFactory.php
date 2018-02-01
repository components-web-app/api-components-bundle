<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Content\Page;

abstract class AbstractComponentFactory implements ComponentFactoryInterface
{
    /**
     * @var ObjectManager
     */
    protected $manager;

    /**
     * @param ObjectManager $manager
     */
    public function __construct(ObjectManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @param AbstractContent $owner
     * @param array|null $ops
     * @return AbstractComponent
     * @throws \InvalidArgumentException
     */
    public function create(AbstractContent $owner, ?array $ops = null): AbstractComponent
    {
        $ops = $this->processOps($ops);
        $component = $this->getComponent();
        $this->setOwner($component, $owner);
        $component->setClassName($ops['className']);
        $this->manager->persist($component);
        return $component;
    }

    /**
     * @param array|null $ops
     * @return array
     */
    public function processOps(?array $ops): array
    {
        if (!$ops) {
            $ops = [];
        }
        return array_filter(
            array_merge(static::defaultOps(), $ops),
            function ($key) {
                return array_key_exists($key, static::defaultOps());
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return array
     */
    public static function defaultOps (): array
    {
        return [ 'className' => null ];
    }

    /**
     * @param AbstractComponent $component
     * @param AbstractContent $parentContent
     * @throws \InvalidArgumentException
     */
    private function setOwner(AbstractComponent $component, AbstractContent $parentContent) {
        $component->setParentContent($parentContent);
    }
}
