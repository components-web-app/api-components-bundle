<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Utility;

use ApiPlatform\Util\ClassInfoTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMInvalidArgumentException;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Exception\BadMethodCallException;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 *
 * @internal
 */
trait ClassMetadataTrait
{
    use ClassInfoTrait;

    protected ?ManagerRegistry $registry;

    /**
     * @required
     */
    protected function initRegistry(ManagerRegistry $registry): void
    {
        $this->registry = $registry;
    }

    /**
     * @param string|object $data
     */
    protected function getClassMetadata($data): ClassMetadata
    {
        return $this->getEntityManager($data)->getClassMetadata($this->getObjectClassFromStringOrObject($data));
    }

    /**
     * @param string|object $data
     */
    protected function getEntityManager($data): EntityManagerInterface
    {
        if (!$this->registry) {
            throw new BadMethodCallException('initRegistry should be called first. Registry property does not exist');
        }

        $em = $this->registry->getManagerForClass($this->getObjectClassFromStringOrObject($data));
        if (!$em instanceof EntityManagerInterface) {
            throw ORMInvalidArgumentException::invalidObject(__CLASS__ . '::' . __FUNCTION__, $data);
        }

        return $em;
    }

    /**
     * @param string|object $object
     */
    private function getObjectClassFromStringOrObject($object): string
    {
        return \is_string($object) ? $object : $this->getObjectClass($object);
    }
}
