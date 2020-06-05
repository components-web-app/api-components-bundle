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

namespace Silverback\ApiComponentsBundle\Security\EventListener;

use Silverback\ApiComponentsBundle\RefreshToken\Storage\RefreshTokenStorageInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class LogoutListener
{
    private RefreshTokenStorageInterface $storage;

    public function __construct(RefreshTokenStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    public function __invoke(LogoutEvent $event): void
    {
        $this->storage->expireAll($event->getToken()->getUser());
    }
}
