<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\MessageHandler;

use Silverback\ApiComponentsBundle\Message\PublishMercureUpdatesMessage;
use Symfony\Component\Mercure\HubRegistry;

final readonly class PublishMercureUpdatesHandler
{
    public function __construct(private HubRegistry $hubRegistry)
    {
    }

    public function __invoke(PublishMercureUpdatesMessage $message): void
    {
        $hub = $this->hubRegistry->getHub($message->hub);
        foreach ($message->updates as $update) {
            $hub->publish($update);
        }
    }
}
