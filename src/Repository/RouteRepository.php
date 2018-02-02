<?php

namespace Silverback\ApiComponentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Symfony\Bridge\Doctrine\RegistryInterface;

class RouteRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Layout::class);
    }
}
