<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Security;

use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AbstractUser) {
            return;
        }

        // user is deleted, show a generic Account Not Found message.
        if (!$user->isEnabled()) {
            throw new DisabledException('This user is currently disabled');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
