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

namespace Silverback\ApiComponentsBundle\Repository\User;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @method AbstractUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbstractUser[]    findAll()
 * @method AbstractUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    private int $passwordRequestTimeout;
    private int $newEmailConfirmTimeout;

    public function __construct(ManagerRegistry $registry, string $entityClass, int $passwordRequestTimeout, int $newEmailConfirmTimeout)
    {
        if (!is_subclass_of($entityClass, AbstractUser::class)) {
            throw new InvalidArgumentException(sprintf('The entity class `%s` used for the repository `%s` must be a subclass of `%s`', $entityClass, __CLASS__, AbstractUser::class));
        }
        parent::__construct($registry, $entityClass);
        $this->passwordRequestTimeout = $passwordRequestTimeout;
        $this->newEmailConfirmTimeout = $newEmailConfirmTimeout;
    }

    public function findOneByEmail($value): ?AbstractUser
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.emailAddress = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneWithPasswordResetToken(string $username): ?AbstractUser
    {
        $minimumRequestDateTime = new \DateTime();
        $minimumRequestDateTime->modify(sprintf('-%d seconds', $this->passwordRequestTimeout));

        return $this->createQueryBuilder('u')
            ->andWhere('u.username = :username')
            ->andWhere('u.newPasswordConfirmationToken IS NOT NULL')
            ->andWhere('u.passwordRequestedAt > :minimumDateTime')
            ->setParameter('username', $username)
            ->setParameter('minimumDateTime', $minimumRequestDateTime)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByUsernameAndNewEmailAddress(string $username, string $email): ?AbstractUser
    {
        $minimumRequestDateTime = new \DateTime();
        $minimumRequestDateTime->modify(sprintf('-%d seconds', $this->newEmailConfirmTimeout));

        return $this->createQueryBuilder('u')
            ->andWhere('u.username = :username')
            ->andWhere('u.newEmailAddress = :email')
            ->andWhere('u.newEmailConfirmationToken IS NOT NULL')
            ->andWhere('u.newEmailAddressChangeRequestedAt > :minimumDateTime')
            ->setParameter('username', $username)
            ->setParameter('email', $email)
            ->setParameter('minimumDateTime', $minimumRequestDateTime)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function loadUserByUsername($usernameOrEmail): ?AbstractUser
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.username = :username')
            ->orWhere('u.emailAddress = :username')
            ->setParameter('username', $usernameOrEmail)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findExistingUserByNewEmail(AbstractUser $user): ?AbstractUser
    {
        $queryBuilder = $this->createQueryBuilder('u');
        $expr = $queryBuilder->expr();
        $queryBuilder
            ->andWhere($expr->eq('u.emailAddress', ':email_address'))
            ->andWhere($expr->neq('u', ':user'))
            ->setParameter('email_address', $user->getNewEmailAddress())
            ->setParameter('user', $user, $this->getClassMetadata()->getTypeOfField('id'));

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }
}
