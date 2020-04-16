<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UnsupportedUser implements UserInterface {
    public function getRoles()
    {
        // TODO: Implement getRoles() method.
    }

    public function getPassword()
    {
        // TODO: Implement getPassword() method.
    }

    public function getSalt()
    {
        // TODO: Implement getSalt() method.
    }

    public function getUsername()
    {
        // TODO: Implement getUsername() method.
    }

    public function eraseCredentials()
    {
        // TODO: Implement eraseCredentials() method.
    }
}
