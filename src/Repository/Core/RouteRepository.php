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
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @author Daniel West <daniel@silverback.is>
 *
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

    public function findOneByIdOrPath(string $idOrRoute): ?Route
    {
        $route = $this->findOneBy(
            [
                'path' => $idOrRoute,
            ]
        );
        if ($route) {
            return $route;
        }

        try {
            $uuid = Uuid::fromString($idOrRoute);

            return $this->find($uuid);
        } catch (InvalidUuidStringException $e) {
        }

        return null;
    }

    /**
     * @return Route[]
     */
    public function findByPageData(AbstractPageData $pageData): array
    {
        $queryBuilder = $this->createQueryBuilder('route');
        $queryBuilder
            ->leftJoin(
                'route.pageData',
                'pageData',
                Join::WITH,
                $queryBuilder->expr()->eq('route', 'pageData.route')
            )
            ->andWhere($queryBuilder->expr()->eq('pageData', ':page_data'))
            ->setParameter('page_data', $pageData);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findConflicts(string $name, string $path): array
    {
        $queryBuilder = $this->createQueryBuilder('route');
        $expr = $queryBuilder->expr();
        $queryBuilder
            ->andWhere($expr->orX(
                $expr->like('route.path', ':path'),
                $expr->like('route.name', ':name'),
            ))
            ->setParameter('path', $path . '%')
            ->setParameter('name', $name . '%');

        return $queryBuilder->getQuery()->getResult();
    }
}
