<?php

namespace Silverback\ApiComponentBundle\DoctrineExtension;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class TablePrefixExtension
{
    private $prefix;

    public function __construct(?string $prefix = '_acb_')
    {
        $this->prefix = $prefix;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        if ($this->prefix === null) {
            return;
        }

        $classMetadata = $eventArgs->getClassMetadata();
        if (strpos($classMetadata->getReflectionClass()->getNamespaceName(), 'Silverback\ApiComponentBundle\\') !== 0) {
            return;
        }

        if (!$classMetadata->isInheritanceTypeSingleTable() || $classMetadata->getName() === $classMetadata->rootEntityName) {
            $classMetadata->setPrimaryTable([
                'name' => $this->prefix . $classMetadata->getTableName()
            ]);
        }

        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if ($mapping['type'] === ClassMetadataInfo::MANY_TO_MANY && $mapping['isOwningSide']) {
                $mappedTableName = $mapping['joinTable']['name'];
                $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = $this->prefix . $mappedTableName;
            }
        }
    }
}
