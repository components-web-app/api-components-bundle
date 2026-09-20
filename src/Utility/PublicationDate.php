<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Utility;

use Doctrine\ORM\QueryBuilder;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class PublicationDate
{
    public static function isActive(?\DateTimeInterface $value): bool
    {
        return null !== $value && new \DateTimeImmutable() >= $value;
    }

    public static function andWhereActive(QueryBuilder $queryBuilder, string $alias, string $fieldName): void
    {
        $parameter = \sprintf('cwa_publication_date_%s_%s', $alias, $fieldName);

        $queryBuilder
            ->andWhere("$alias.$fieldName IS NOT NULL")
            ->andWhere("$alias.$fieldName <= :$parameter")
            ->setParameter($parameter, new \DateTime());
    }
}
