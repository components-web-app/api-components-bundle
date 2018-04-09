<?php

namespace Silverback\ApiComponentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\Dynamic\AbstractDynamicPage;
use Symfony\Bridge\Doctrine\RegistryInterface;

class ComponentRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, AbstractComponent::class);
    }

    public function findByDynamicPage(AbstractDynamicPage $page): array
    {
        $qb = $this->createQueryBuilder('page');
        $qb
            ->andWhere(
                $qb->expr()->eq('page.dynamicPageClass', \get_class($page))
            )
        ;
        return $qb->getQuery()->getArrayResult();
    }
}
