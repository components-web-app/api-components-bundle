<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Symfony\Bridge\Doctrine\RegistryInterface;

class RouteRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Route::class);
    }
}
