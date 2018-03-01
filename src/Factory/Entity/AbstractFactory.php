<?php

namespace Silverback\ApiComponentBundle\Factory\Entity;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Exception\InvalidEntityException;
use Silverback\ApiComponentBundle\Exception\InvalidFactoryOptionException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractFactory implements FactoryInterface
{
    public const COMPONENT_CLASSES = [
        'className' => null,
        'parent' => null
    ];

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
     * @param $component
     * @param array|null $ops
     * @param array $ignoreOps
     */
    protected function init($component, ?array $ops = null, ?array $ignoreOps = null): void
    {
        $this->setOptions($ops);
        foreach ($this->ops as $op=>$value) {
            if (
                null !== $value &&
                (
                    null === $ignoreOps ||
                    !\in_array($op, $ignoreOps, true)
                )
            ) {
                $setter = $this->findSetterMethod($component, $op);
                if (\is_array($value)) {
                    $component->$setter(...$value);
                } else {
                    $component->$setter($value);
                }
            }
        }
        $this->manager->persist($component);
    }

    /**
     * @param $component
     * @param $op
     * @return string
     */
    private function findSetterMethod($component, $op): string
    {
        $prefixes = ['set', 'add'];
        $postfix = ucfirst($op);
        foreach ($prefixes as $prefix) {
            $setter = $prefix . $postfix;
            if (method_exists($component, $setter)) {
                return $setter;
            }
        }
        throw new \RuntimeException(sprintf('A preconfigured option `%s` has no setter or adder method', $op));
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
     * @param $component
     * @return bool
     * @throws \Silverback\ApiComponentBundle\Exception\InvalidEntityException
     */
    protected function validate($component): bool
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
        return [];
    }

    /**
     * @param array|null $ops
     */
    abstract public function create(?array $ops = null);
}
