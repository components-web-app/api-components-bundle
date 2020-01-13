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

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        if (null === $this->prefix) {
            return;
        }

        $classMetadata = $eventArgs->getClassMetadata();
        if (0 !== strpos($classMetadata->getReflectionClass()->getNamespaceName(), 'Silverback\ApiComponentBundle\\')) {
            return;
        }

        $converter = new CamelCaseToSnakeCaseNameConverter();
        if (!$classMetadata->isInheritanceTypeSingleTable() || $classMetadata->getName() === $classMetadata->rootEntityName) {
            $classMetadata->setPrimaryTable([
                'name' => $this->prefix . $converter->normalize($classMetadata->getTableName()),
            ]);
        }

        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if (ClassMetadataInfo::MANY_TO_MANY === $mapping['type'] && $mapping['isOwningSide']) {
                $mappedTableName = $mapping['joinTable']['name'];
                $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = $this->prefix . $mappedTableName;
            }
        }
    }
}
