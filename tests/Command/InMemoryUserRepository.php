<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Command;

use Doctrine\ORM\NonUniqueResultException;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /**
     * @param AbstractUser[] $users
     */
    public function __construct(private readonly array $users)
    {
    }

    public function findOneByEmail(string $value): ?AbstractUser
    {
        foreach ($this->users as $user) {
            if (strtolower((string) $user->getEmailAddress()) === strtolower($value)) {
                return $user;
            }
        }

        return null;
    }

    public function findOneWithPasswordResetToken(string $username): ?AbstractUser
    {
        return null;
    }

    public function findOneByUsernameAndNewEmailAddress(string $username, string $email): ?AbstractUser
    {
        return null;
    }

    public function loadUserByIdentifier(string $identifier): ?AbstractUser
    {
        $matches = array_values(array_filter(
            $this->users,
            static fn (AbstractUser $user): bool => \in_array(strtolower($identifier), [strtolower((string) $user->getUsername()), strtolower((string) $user->getEmailAddress())], true)
        ));

        if (\count($matches) > 1) {
            throw new NonUniqueResultException();
        }

        return $matches[0] ?? null;
    }

    public function findExistingUserByNewEmail(AbstractUser $user): ?AbstractUser
    {
        return null;
    }
}
