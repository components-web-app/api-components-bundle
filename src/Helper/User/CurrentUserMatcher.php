<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\User;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class CurrentUserMatcher
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public function isCurrentUser(AbstractUser $user): bool
    {
        $id = $user->getId();
        $current = $this->tokenStorage->getToken()?->getUser();

        return null !== $id && $current instanceof AbstractUser && $id->equals($current->getId());
    }
}
