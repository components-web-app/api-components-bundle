<?php

namespace Silverback\ApiComponentBundle\Repository\Content\Page\Dynamic;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method DynamicContent|null find($id, $lockMode = null, $lockVersion = null)
 * @method DynamicContent|null findOneBy(array $criteria, array $orderBy = null)
 * @method DynamicContent[]    findAll()
 * @method DynamicContent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DynamicContentRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, DynamicContent::class);
    }
}
