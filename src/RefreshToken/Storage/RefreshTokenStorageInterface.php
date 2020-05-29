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

namespace Silverback\ApiComponentsBundle\RefreshToken\Storage;

use Silverback\ApiComponentsBundle\RefreshToken\RefreshToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
interface RefreshTokenStorageInterface
{
    public function findOneByUser(UserInterface $user): ?RefreshToken;

    public function create(UserInterface $user): void;

    public function expireAll(?UserInterface $user): void;
}
