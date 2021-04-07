<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Repository\Component;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Silverback\ApiComponentBundle\Entity\Component\ComponentLocation;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ComponentLocation|null find($id, $lockMode = null, $lockVersion = null)
 * @method ComponentLocation|null findOneBy(array $criteria, array $orderBy = null)
 * @method ComponentLocation[]    findAll()
 * @method ComponentLocation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ComponentLocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ComponentLocation::class);
    }
}
