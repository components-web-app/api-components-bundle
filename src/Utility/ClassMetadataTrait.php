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
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMInvalidArgumentException;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Exception\BadMethodCallException;

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

    private function getClassMetadata(object $data): ClassMetadata
    {
        return $this->getEntityManager($data)->getClassMetadata($this->getObjectClass($data));
    }

    private function getEntityManager(object $data): EntityManagerInterface
    {
        if (!$this->registry) {
            throw new BadMethodCallException('initRegistry should be called first. Registry property does not exist');
        }

        $em = $this->registry->getManagerForClass($this->getObjectClass($data));
        if (!$em instanceof EntityManagerInterface) {
            throw ORMInvalidArgumentException::invalidObject(__CLASS__ . '::' . __FUNCTION__, $data);
        }

        return $em;
    }
}
