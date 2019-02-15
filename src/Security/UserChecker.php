<?php

namespace Silverback\ApiComponentBundle\Security;

use Silverback\ApiComponentBundle\Entity\User\User;
use Silverback\ApiComponentBundle\Exception\UserDisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    /**
     * @param UserInterface $user
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
    }

    /**
     * @param UserInterface $user
     */
    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        if (!$user->isEnabled()) {
            throw new UserDisabledException('Your account is not currently enabled.');
        }
    }
}
