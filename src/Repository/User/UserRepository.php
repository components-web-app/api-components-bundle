<?php

namespace Silverback\ApiComponentBundle\Repository\User;

use Silverback\ApiComponentBundle\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    private $passwordRequestTimeout;

    public function __construct(RegistryInterface $registry, int $passwordRequestTimeout, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
        $this->passwordRequestTimeout = $passwordRequestTimeout;
    }

    public function findOneByEmail($value): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findOneByPasswordResetToken(string $username, string $token)
    {
        $minimumRequestDateTime = new \DateTime();
        $minimumRequestDateTime->modify(sprintf('-%d seconds', $this->passwordRequestTimeout));
        return $this->createQueryBuilder('u')
            ->andWhere('u.username = :username')
            ->andWhere('u.passwordResetConfirmationToken = :token')
            ->andWhere('u.passwordRequestedAt > :passwordRequestedAt')
            ->setParameter('username', $username)
            ->setParameter('token', $token)
            ->setParameter('passwordRequestedAt', $minimumRequestDateTime)
            ->getQuery()
            ->getOneOrNullResult()
            ;
    }
}
