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

namespace Silverback\ApiComponentBundle\Utility;

use ApiPlatform\Core\Util\ClassInfoTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\ORMInvalidArgumentException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 *
 * @internal
 */
trait ClassMetadataTrait
{
    use ClassInfoTrait;

    private ?ManagerRegistry $registry;

    private function initRegistry(ManagerRegistry $registry): void
    {
        $this->registry = $registry;
    }

    private function getClassMetadata(object $data): ClassMetadataInfo
    {
        /* @var ClassMetadataInfo $classMetadata */
        return $this->getEntityManager($data)->getClassMetadata($this->getObjectClass($data));
    }

    private function getEntityManager(object $data): EntityManagerInterface
    {
        /** @var EntityManagerInterface|null $em */
        $em = $this->registry->getManagerForClass($this->getObjectClass($data));
        if (!$em) {
            throw ORMInvalidArgumentException::invalidObject(__CLASS__ . '::' . __FUNCTION__, $data);
        }

        return $em;
    }
}
