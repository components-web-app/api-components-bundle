<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Repository\Component;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Component\ComponentLocation;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ComponentLocation|null find($id, $lockMode = null, $lockVersion = null)
 * @method ComponentLocation|null findOneBy(array $criteria, array $orderBy = null)
 * @method ComponentLocation[]    findAll()
 * @method ComponentLocation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ComponentLocationRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ComponentLocation::class);
    }

    public function findByDynamicPage(string $dynamicPageClass): Collection
    {
        $qb = $this->createQueryBuilder('location');
        $qb
            ->andWhere(
                $qb->expr()->eq('location.dynamicPageClass', ':cls')
            )
            ->setParameter('cls', $dynamicPageClass)
            ->addOrderBy('location.sort', 'ASC');

        $result = new ArrayCollection($qb->getQuery()->getResult());

        $uow = $this->getEntityManager()->getUnitOfWork();
        $scheduledInsertions = $uow->getScheduledEntityInsertions();
        foreach ($scheduledInsertions as $scheduledInsertion) {
            if ($scheduledInsertion instanceof ComponentLocation && $scheduledInsertion->getDynamicPageClass() === $dynamicPageClass) {
                $result->add($scheduledInsertion);
            }
        }
        return $result;
    }
}
