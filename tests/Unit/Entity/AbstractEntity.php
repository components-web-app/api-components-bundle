<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractEntity extends TestCase
{
    /**
     * @var ValidatorInterface
     */
    protected static $validator;

    public static function setUpBeforeClass()
    {
        self::$validator = Validation::createValidatorBuilder()
            ->addMethodMapping('loadValidatorMetadata')
            ->getValidator();
    }

    /**
     * @param mixed $entity
     * @return array
     */
    protected function getConstraints($entity): array
    {
        $constraints = [];
        /** @var ClassMetadata $metadata */
        $metadata = self::$validator->getMetadataFor($entity);
        $constraints['_class'] = $metadata->getConstraints();
        $props = $metadata->getConstrainedProperties();
        foreach ($props as $prop) {
            /** @var PropertyMetadata[] $propMetas */
            $propMetas = $metadata->getPropertyMetadata($prop);
            foreach ($propMetas as $propMeta) {
                $constraints[$prop] = $propMeta->getConstraints();
            }
        }
        return $constraints;
    }

    /**
     * @param $instance
     * @param $array
     * @return bool
     */
    protected function instanceInArray($instance, $array): bool
    {
        foreach ($array as $item) {
            if ($item instanceof $instance) {
                return true;
            }
        }
        return false;
    }
}
