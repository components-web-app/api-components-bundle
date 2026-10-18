<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Message;

use Symfony\Component\Mercure\Update;

final readonly class PublishMercureUpdatesMessage
{
    /**
     * @param list<Update> $updates
     */
    public function __construct(public array $updates, public ?string $hub = null)
    {
    }
}
