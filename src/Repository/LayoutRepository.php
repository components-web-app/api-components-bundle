<?php

namespace Silverback\ApiComponentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Class LayoutRepository
 * @package Silverback\ApiComponentBundle\Repository
 * @method Layout|null findOneBy(array $criteria, array $orderBy = null)
 */
class LayoutRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Layout::class);
    }
}
