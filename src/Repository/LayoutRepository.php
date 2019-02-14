<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Layout|null find($id, $lockMode = null, $lockVersion = null)
 * @method Layout|null findOneBy(array $criteria, array $orderBy = null)
 * @method Layout[]    findAll()
 * @method Layout[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LayoutRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Layout::class);
    }
}
