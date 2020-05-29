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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractRefreshToken;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @method AbstractRefreshToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractRefreshToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbstractRefreshToken[]    findAll()
 * @method AbstractRefreshToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        if (!is_subclass_of($entityClass, AbstractRefreshToken::class)) {
            throw new InvalidArgumentException(sprintf('The entity class `%s` used for the repository `%s` must be a subclass of `%s`', $entityClass, __CLASS__, AbstractRefreshToken::class));
        }
        parent::__construct($registry, $entityClass);
    }

    public function findOneByUser(UserInterface $user): ?AbstractRefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->andWhere('rt.user = :user')->setParameter('user', $user)
            ->andWhere('rt.expiredAt > :now')->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
