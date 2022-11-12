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

namespace Silverback\ApiComponentsBundle\Repository\Core;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @method ComponentPosition|null find($id, $lockMode = null, $lockVersion = null)
 * @method ComponentPosition|null findOneBy(array $criteria, array $orderBy = null)
 * @method ComponentPosition[]    findAll()
 * @method ComponentPosition[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ComponentPositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ComponentPosition::class);
    }

    public function findByComponent(ComponentInterface $component): array
    {
        return $this->findBy([
            'component' => $component,
        ]);
    }

    public function findByPageDataProperties(array $properties): array
    {
        $qb = $this->createQueryBuilder('cp');
        $expr = $qb->expr();
        foreach ($properties as $index => $property) {
            $key = sprintf(':positionName%d', $index);
            $qb->orWhere($expr->eq('cp.pageDataProperty', $key));
            $qb->setParameter($key, $property);
        }

        return $qb->getQuery()->getResult();
    }
}
