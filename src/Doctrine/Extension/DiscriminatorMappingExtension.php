<?php

namespace Silverback\ApiComponentBundle\Doctrine\Extension;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Psr\Cache\InvalidArgumentException;
use Silverback\ApiComponentBundle\Entity\Core\AbstractComponent;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class DiscriminatorMappingExtension
{
    private MappingDriver $driver;

    /**
     * @throws ORMException
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->driver = $em->getConfiguration()->getMetadataDriverImpl();
    }

    /**
     * @throws MappingException
     * @throws InvalidArgumentException
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        $reflectionName = $classMetadata->getReflectionClass()->getName();
        if ($reflectionName !== AbstractComponent::class) {
            return;
        }

        $this->addDiscriminatorMap($classMetadata);
    }

    /**
     * @throws MappingException
     * @throws InvalidArgumentException
     */
    protected function addDiscriminatorMap(ClassMetadata $classMetadata): void
    {
        if ($classMetadata->isRootEntity() && ! $classMetadata->isInheritanceTypeNone()) {
            $this->addDefaultDiscriminatorMap($classMetadata);
        }
    }

    /**
     * Adds a default discriminator map if no one is given
     *
     * If an entity is of any inheritance type and does not contain a
     * discriminator map, then the map is generated automatically. This process
     * is usually expensive computation wise, however by using caching, the only
     * part which is always called checks whether the class names have changed. If not,
     * no additional computation is required.
     *
     * The automatically generated discriminator map contains the lowercase short name of
     * each class as key.
     *
     * @throws MappingException
     * @throws InvalidArgumentException
     */
    private function addDefaultDiscriminatorMap(ClassMetadata $class): void
    {
        $cache = new FilesystemAdapter();
        $allClasses = $this->driver->getAllClassNames();
        $className = $this->getShortName($class->name);
        $cachedClasses = $cache->getItem(sprintf('silverback_api_component.doctrine.driver_classes.%s', $className));
        $cachedMap = $cache->getItem(sprintf('silverback_api_component.doctrine.components_discriminator_map.%s', $className));
        if (!$cachedClasses->isHit() || $cachedClasses->get() !== $allClasses) {
            $cachedClasses->set($allClasses);
            $cache->save($cachedClasses);

            $cachedMap->set($this->getDiscriminatorMap($class, $allClasses));
            $cache->save($cachedMap);
        }
        $map = $cachedMap->get();
        $class->setDiscriminatorMap($map);
    }

    /**
     * @throws MappingException
     */
    private function getDiscriminatorMap(ClassMetadata $class, array $allClasses): array
    {
        $fqcn = $class->getName();
        $map = [$this->getShortName($class->name) => $fqcn];

        $duplicates = [];
        foreach ($allClasses as $subClassCandidate) {
            if (is_subclass_of($subClassCandidate, $fqcn)) {
                $shortName = $this->getShortName($subClassCandidate);

                if (isset($map[$shortName])) {
                    $duplicates[] = $shortName;
                }

                $map[$shortName] = $subClassCandidate;
            }
        }
        if ($duplicates) {
            throw MappingException::duplicateDiscriminatorEntry($class->name, $duplicates, $map);
        }
        return $map;
    }


    /**
     * Gets the lower-case short name of a class.
     */
    private function getShortName($className): string
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter();
        $parts = explode("\\", $className);
        $lastPart = end($parts);
        return $nameConverter->normalize($lastPart);
    }
}
