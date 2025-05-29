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
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @method SiteConfigParameter|null find($id, $lockMode = null, $lockVersion = null)
 * @method SiteConfigParameter|null findOneBy(array $criteria, array $orderBy = null)
 * @method SiteConfigParameter[]    findAll()
 * @method SiteConfigParameter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SiteConfigParameterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteConfigParameter::class);
    }
}
