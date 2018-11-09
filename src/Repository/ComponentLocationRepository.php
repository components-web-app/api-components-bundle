<?php

namespace Silverback\ApiComponentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Silverback\ApiComponentBundle\Entity\Content\Component\ComponentLocation;
use Silverback\ApiComponentBundle\Entity\Content\Dynamic\AbstractDynamicPage;
use Symfony\Bridge\Doctrine\RegistryInterface;

class ComponentLocationRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ComponentLocation::class);
    }

    public function findByDynamicPage(AbstractDynamicPage $page): array
    {
        $qb = $this->createQueryBuilder('location');
        $qb
            ->andWhere(
                $qb->expr()->eq('location.dynamicPageClass', ':cls')
            )
            ->setParameter('cls', \get_class($page))
            ->addOrderBy('location.sort', 'ASC');
        return $qb->getQuery()->getResult();
    }
}
