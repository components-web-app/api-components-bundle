<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Repository\Core;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @method ComponentGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method ComponentGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method ComponentGroup[]    findAll()
 * @method ComponentGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ComponentGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ComponentGroup::class);
    }

    public function findOneByIdOrReference(string $idOrRef): ?ComponentGroup
    {
        $byRef = $this->findOneBy([
            'reference' => $idOrRef,
        ]);
        if ($byRef) {
            return $byRef;
        }

        try {
            $uuid = Uuid::fromString($idOrRef);

            return $this->find($uuid);
        } catch (InvalidUuidStringException $e) {
        }

        return null;
    }
}
