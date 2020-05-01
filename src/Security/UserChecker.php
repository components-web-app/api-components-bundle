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

namespace Silverback\ApiComponentsBundle\Security;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    private bool $denyUnverifiedLogin;

    public function __construct(bool $denyUnverifiedLogin = true)
    {
        $this->denyUnverifiedLogin = $denyUnverifiedLogin;
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AbstractUser) {
            return;
        }

        if (!$user->isEnabled()) {
            throw new DisabledException('This user is currently disabled');
        }

        if ($this->denyUnverifiedLogin && !$user->isEmailAddressVerified()) {
            throw new DisabledException('Please verify your email address before logging in. If you did not receive a confirmation email please try resetting your password using the forgot password feature.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
