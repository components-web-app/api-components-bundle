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

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UnsupportedUser implements UserInterface
{
    public function getRoles(): array
    {
        return [];
    }

    public function getPassword()
    {
    }

    public function getSalt()
    {
    }

    public function getUsername()
    {
    }

    public function eraseCredentials()
    {
    }

    public function getUserIdentifier(): string
    {
        return '';
    }
}
