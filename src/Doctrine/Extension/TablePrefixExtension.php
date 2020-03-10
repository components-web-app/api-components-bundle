<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Doctrine\Extension;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class TablePrefixExtension
{
    private $prefix;

    public function __construct(?string $prefix = '_acb_')
    {
        $this->prefix = $prefix;
    }

    private function supportsClass(ClassMetadata $classMetadata): bool
    {
        if (null === $this->prefix) {
            return false;
        }

        if (0 !== strpos($classMetadata->getReflectionClass()->getNamespaceName(), 'Silverback\ApiComponentBundle\\')) {
            return false;
        }

        return true;
    }

    private function setPrimaryTable(ClassMetadata $classMetadata): void
    {
        $converter = new CamelCaseToSnakeCaseNameConverter();
        if (!$classMetadata->isInheritanceTypeSingleTable() || $classMetadata->getName() === $classMetadata->rootEntityName) {
            $classMetadata->setPrimaryTable([
                'name' => $this->prefix . $converter->normalize($classMetadata->getTableName()),
            ]);
        }
    }

    private function setJoinTableName(ClassMetadata $classMetadata): void
    {
        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if (ClassMetadataInfo::MANY_TO_MANY === $mapping['type'] && $mapping['isOwningSide'] && !\array_key_exists('inherited', $mapping)) {
                $mappedTableName = $mapping['joinTable']['name'];
                $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = $this->prefix . $mappedTableName;
            }
        }
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        if (!$this->supportsClass($classMetadata)) {
            return;
        }
        $this->setPrimaryTable($classMetadata);
        $this->setJoinTableName($classMetadata);
    }
}
