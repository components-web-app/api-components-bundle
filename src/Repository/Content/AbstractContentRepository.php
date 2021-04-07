<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Repository\Content;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AbstractContent|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractContent|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbstractContent[]    findAll()
 * @method AbstractContent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AbstractContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbstractContent::class);
    }
}
