<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Repository\User;

use Doctrine\ORM\NonUniqueResultException;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;

trait FindUserByUsernameTrait
{
    private function findUserByUsernameIn(UserRepositoryInterface $repository, string $username): ?AbstractUser
    {
        try {
            $user = $repository->loadUserByIdentifier($username);
        } catch (NonUniqueResultException) {
            return null;
        }

        return null !== $user && strtolower((string) $user->getUsername()) === strtolower($username) ? $user : null;
    }
}
