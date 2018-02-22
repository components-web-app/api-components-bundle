<?php

namespace Silverback\ApiComponentBundle\Factory\Fixtures\Component;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Exception\InvalidFactoryOptionException;

abstract class AbstractComponentFactory implements ComponentFactoryInterface
{
    /**
     * @var ObjectManager
     */
    protected $manager;

    /**
     * @var null|array
     */
    protected $ops;

    /**
     * @param ObjectManager $manager
     */
    public function __construct(ObjectManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @param AbstractComponent $component
     * @param array|null $ops
     */
    protected function init(AbstractComponent $component, ?array $ops = null): void
    {
        $this->setOptions($ops);
        $component->setClassName($this->ops['className']);
        $this->manager->persist($component);
    }

    /**
     * @return array
     */
    protected static function defaultOps(): array
    {
        return [
            'className' => null
        ];
    }

    /**
     * @param array|null $ops
     * @throws \Silverback\ApiComponentBundle\Exception\InvalidFactoryOptionException
     */
    protected function setOptions(?array $ops): void
    {
        if (!$ops) {
            $ops = [];
        }
        $this->ops = array_filter(
            array_merge(static::defaultOps(), $ops),
            function ($key) {
                if (!array_key_exists($key, static::defaultOps())) {
                    throw new InvalidFactoryOptionException(
                        sprintf('%s is not a valid option for the factory %s', $key, \get_class($this))
                    );
                }
                return true;
            },
            ARRAY_FILTER_USE_KEY
        );
    }
}
