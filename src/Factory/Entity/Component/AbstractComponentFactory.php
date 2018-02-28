<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Exception\InvalidEntityException;
use Silverback\ApiComponentBundle\Exception\InvalidFactoryOptionException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @param ObjectManager $manager
     * @param ValidatorInterface $validator
     */
    public function __construct(
        ObjectManager $manager,
        ValidatorInterface $validator
    ) {
        $this->manager = $manager;
        $this->validator = $validator;
    }

    /**
     * @param AbstractComponent $component
     * @param array|null $ops
     * @throws \Silverback\ApiComponentBundle\Exception\InvalidFactoryOptionException
     */
    protected function init($component, ?array $ops = null): void
    {
        $this->setOptions($ops);
        $component->setClassName($this->ops['className']);
        $this->manager->persist($component);
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

    /**
     * @param AbstractComponent $component
     * @return bool
     * @throws \Silverback\ApiComponentBundle\Exception\InvalidEntityException
     */
    protected function validate(AbstractComponent $component): bool
    {
        $errors = $this->validator->validate($component);
        if (\count($errors)) {
            throw new InvalidEntityException($errors);
        }
        return true;
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
     */
    abstract public function create(?array $ops = null);
}
