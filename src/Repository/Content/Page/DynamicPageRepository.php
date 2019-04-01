<?php

namespace Silverback\ApiComponentBundle\Repository\Content\Page;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Silverback\ApiComponentBundle\Entity\Content\Page\DynamicPage;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method DynamicPage|null find($id, $lockMode = null, $lockVersion = null)
 * @method DynamicPage|null findOneBy(array $criteria, array $orderBy = null)
 * @method DynamicPage[]    findAll()
 * @method DynamicPage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DynamicPageRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, DynamicPage::class);
    }
}
