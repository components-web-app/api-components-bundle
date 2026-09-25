<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\EventListener\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class MappedSuperclassDiscriminatorMapListener
{
    /**
     * @param list<class-string> $rootClasses
     */
    public function __construct(private readonly array $rootClasses = [AbstractComponent::class, AbstractPageData::class])
    {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();
        if (!\in_array($metadata->getName(), $this->rootClasses, true) || !$metadata->isRootEntity()) {
            return;
        }

        $configuration = $eventArgs->getObjectManager()->getConfiguration();
        $driver = $configuration->getMetadataDriverImpl();
        if (null === $driver) {
            return;
        }
        $reflectionService = new RuntimeReflectionService();

        $mappedSuperclasses = [];
        foreach ($metadata->discriminatorMap as $className) {
            if ($className === $metadata->getName()) {
                continue;
            }
            $candidate = new ClassMetadata($className, $configuration->getNamingStrategy(), $configuration->getTypedFieldMapper());
            $candidate->initializeReflection($reflectionService);
            $driver->loadMetadataForClass($className, $candidate);
            if ($candidate->isMappedSuperclass) {
                $mappedSuperclasses[] = $className;
            }
        }

        if ([] === $mappedSuperclasses) {
            return;
        }

        $metadata->discriminatorMap = array_filter(
            $metadata->discriminatorMap,
            static fn (string $className) => !\in_array($className, $mappedSuperclasses, true)
        );
        $metadata->subClasses = array_values(array_filter(
            $metadata->subClasses,
            static fn (string $className) => !\in_array($className, $mappedSuperclasses, true)
        ));
    }
}
