<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Repository\Route;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Route|null find($id, $lockMode = null, $lockVersion = null)
 * @method Route|null findOneBy(array $criteria, array $orderBy = null)
 * @method Route[]    findAll()
 * @method Route[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RouteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Route::class);
    }

    public function findOneByIdOrRoute(string $idOrRoute)
    {
        $route = $this->find($idOrRoute);
        if ($route) {
            return $route;
        }
        return $this->findOneBy([
            'route' => $idOrRoute
        ]);
    }
}
