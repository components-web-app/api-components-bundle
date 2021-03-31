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

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @method AbstractUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbstractUser[]    findAll()
 * @method AbstractUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface UserRepositoryInterface extends UserLoaderInterface
{
    public function findOneByEmail(string $value): ?AbstractUser;

    public function findOneWithPasswordResetToken(string $username): ?AbstractUser;

    public function findOneByUsernameAndNewEmailAddress(string $username, string $email): ?AbstractUser;

    public function loadUserByUsername(string $username): ?AbstractUser;

    public function findExistingUserByNewEmail(AbstractUser $user): ?AbstractUser;
}
