<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator\Site;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @internal
 */
final class EntityStateReader
{
    private const array COMPONENT_STRUCTURE = ['componentGroups', 'componentPositions'];

    /** @var array<class-string, object|null> */
    private array $freshInstances = [];

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly UploadableAttributeReaderInterface $uploadableAttributeReader,
        private readonly PublishableAttributeReader $publishableAttributeReader,
        private readonly TimestampedAttributeReader $timestampedAttributeReader,
    ) {
    }

    /**
     * @param list<string> $skip
     *
     * @return list<PropertyValue>
     */
    public function read(object $entity, array $skip = []): array
    {
        $this->registry->getManagerForClass($entity::class)?->initializeObject($entity);
        $metadata = $this->metadata($entity);
        $skip = [...$skip, ...self::COMPONENT_STRUCTURE, ...$this->managedFields($entity)];

        $uploadable = [];
        if ($this->uploadableAttributeReader->isConfigured($entity)) {
            foreach ($this->uploadableAttributeReader->getConfiguredProperties($entity, true) as $fileProperty => $configuration) {
                $uploadable[$fileProperty] = $configuration;
                $skip[] = $configuration->property;
            }
        }

        $values = [];
        foreach ($metadata->getFieldNames() as $field) {
            if ($metadata->isIdentifier($field) || \in_array($field, $skip, true)) {
                continue;
            }
            $value = $metadata->getFieldValue($entity, $field);
            if (!$this->isDefaultValue($metadata, $field, $value)) {
                $values[] = $this->value(PropertyValue::FIELD, $entity, $field, $value);
            }
        }

        foreach ($uploadable as $fileProperty => $configuration) {
            $path = $metadata->getFieldValue($entity, $configuration->property);
            if (null !== $path) {
                $values[] = $this->value(PropertyValue::FILE, $entity, $fileProperty, ['adapter' => $configuration->adapter, 'path' => $path]);
            }
        }

        foreach ($metadata->getAssociationNames() as $association) {
            if (\in_array($association, $skip, true) || $metadata->isAssociationInverseSide($association)) {
                continue;
            }
            $value = $metadata->getFieldValue($entity, $association);
            if (null === $value || (is_iterable($value) && 0 === \count($value))) {
                continue;
            }
            $kind = match (true) {
                is_iterable($value) => PropertyValue::COLLECTION,
                $value instanceof AbstractComponent => PropertyValue::COMPONENT,
                $value instanceof Route => PropertyValue::ROUTE,
                default => PropertyValue::ENTITY,
            };
            $values[] = $this->value($kind, $entity, $association, $value);
        }

        return $values;
    }

    public function publishedAt(object $entity): ?\DateTimeInterface
    {
        if (!$this->publishableAttributeReader->isConfigured($entity)) {
            return null;
        }
        $value = $this->metadata($entity)->getFieldValue($entity, $this->publishableAttributeReader->getConfiguration($entity)->fieldName);

        return $value instanceof \DateTimeInterface ? $value : null;
    }

    public function draftOf(AbstractComponent $component): ?AbstractComponent
    {
        if (!$this->publishableAttributeReader->isConfigured($component)) {
            return null;
        }
        $draft = $this->metadata($component)->getFieldValue($component, $this->publishableAttributeReader->getConfiguration($component)->reverseAssociationName);

        return $draft instanceof AbstractComponent ? $draft : null;
    }

    public function publishedOf(AbstractComponent $component): ?AbstractComponent
    {
        if (!$this->publishableAttributeReader->isConfigured($component)) {
            return null;
        }
        $published = $this->metadata($component)->getFieldValue($component, $this->publishableAttributeReader->getConfiguration($component)->associationName);

        return $published instanceof AbstractComponent ? $published : null;
    }

    /**
     * @return class-string
     */
    public function className(object $entity): string
    {
        return $this->metadata($entity)->getName();
    }

    /**
     * @return list<string>
     */
    private function managedFields(object $entity): array
    {
        $fields = [];
        if ($this->timestampedAttributeReader->isConfigured($entity)) {
            $configuration = $this->timestampedAttributeReader->getConfiguration($entity);
            $fields[] = $configuration->createdAtField;
            $fields[] = $configuration->modifiedAtField;
        }
        if ($this->publishableAttributeReader->isConfigured($entity)) {
            $configuration = $this->publishableAttributeReader->getConfiguration($entity);
            $fields[] = $configuration->fieldName;
            $fields[] = $configuration->associationName;
            $fields[] = $configuration->reverseAssociationName;
        }

        return $fields;
    }

    private function value(string $kind, object $entity, string $property, mixed $value): PropertyValue
    {
        $reflection = new \ReflectionClass($entity);
        if ($reflection->hasProperty($property)) {
            $reflectionProperty = $reflection->getProperty($property);
            if ($reflectionProperty->isPublic() && !$reflectionProperty->isReadOnly()) {
                return new PropertyValue($kind, $property, $value, true);
            }
        }
        $setter = 'set' . ucfirst($property);
        if ($reflection->hasMethod($setter) && $reflection->getMethod($setter)->isPublic()) {
            return new PropertyValue($kind, $property, $value, true, $setter);
        }

        return new PropertyValue($kind, $property, $value, false);
    }

    private function isDefaultValue(ClassMetadata $metadata, string $field, mixed $value): bool
    {
        $fresh = $this->freshInstance($metadata->getName());
        for ($class = new \ReflectionClass($metadata->getName()); false !== $class; $class = $class->getParentClass()) {
            if (!$class->hasProperty($field)) {
                continue;
            }
            $property = $class->getProperty($field);
            if (null !== $fresh) {
                return $property->isInitialized($fresh) ? $value === $property->getValue($fresh) : null === $value;
            }

            return $property->hasDefaultValue() ? $value === $property->getDefaultValue() : null === $value;
        }

        return null === $value;
    }

    /**
     * @param class-string $class
     */
    private function freshInstance(string $class): ?object
    {
        if (!\array_key_exists($class, $this->freshInstances)) {
            $reflection = new \ReflectionClass($class);
            $constructor = $reflection->getConstructor();
            $this->freshInstances[$class] = $reflection->isInstantiable() && (null === $constructor || 0 === $constructor->getNumberOfRequiredParameters()) ? $reflection->newInstance() : null;
        }

        return $this->freshInstances[$class];
    }

    private function metadata(object $entity): ClassMetadata
    {
        $metadata = $this->registry->getManagerForClass($entity::class)?->getClassMetadata($entity::class);
        if (!$metadata instanceof ClassMetadata) {
            throw new \LogicException(\sprintf('%s is not mapped by the Doctrine ORM.', $entity::class));
        }

        return $metadata;
    }
}
