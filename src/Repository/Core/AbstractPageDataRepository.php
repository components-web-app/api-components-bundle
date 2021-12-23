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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @method AbstractPageData|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractPageData|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbstractPageData[]    findAll()
 * @method AbstractPageData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AbstractPageDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbstractPageData::class);
    }

    /**
     * @return AbstractPageData[]
     */
    public function findByNestedComponent(AbstractComponent $component): array
    {
        $queryBuilder = $this->createQueryBuilder('pageData');
        $queryBuilder
            ->leftJoin(
                'pageData.page',
                'pageData_page',
                Join::WITH,
                $queryBuilder->expr()->eq('pageData_page', 'pageData.page')
            )
            ->leftJoin(
                'pageData_page.componentCollections',
                'page_data_cc'
            )
            ->leftJoin(
                'page_data_cc.componentPositions',
                'page_data_pos'
            )
            ->leftJoin(
                'page_data_pos.component',
                'page_data_component',
                Join::WITH,
                $queryBuilder->expr()->eq('page_data_pos.component', 'page_data_component')
            )
            ->andWhere(
                $queryBuilder->expr()->eq('page_data_component', ':component')
            )
            ->setParameter('component', $component);

        return $queryBuilder->getQuery()->getResult();
    }
}
