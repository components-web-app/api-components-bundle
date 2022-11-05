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

namespace Silverback\ApiComponentsBundle\EventListener\Doctrine;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

/**
 * Purges desired resources on when doctrine is flushed from the proxy cache.
 * Will purge resources with related mapping too.
 *
 * @author Daniel West <daniel@silverback.is>
 *
 * @experimental
 */
interface DoctrineResourceFlushListenerInterface
{
    public function preUpdate(PreUpdateEventArgs $eventArgs): void;

    public function onFlush(OnFlushEventArgs $eventArgs): void;

    public function postFlush(PostFlushEventArgs $eventArgs): void;
}
