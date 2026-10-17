<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\HttpCache\Warm;

use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Message\WarmHttpCacheMessage;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class HttpCacheWarmDispatcher
{
    public const array ACCEPT_ENCODINGS = [ModuleRequestHeaders::ACCEPT_ENCODING, 'br'];

    public function __construct(
        private ?MessageBusInterface $messageBus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function dispatch(): void
    {
        if (null === $this->messageBus) {
            $this->logger?->warning('http_cache.warm_after_flush is enabled but no message bus is configured, so the HTTP cache was not warmed after the flush');

            return;
        }

        foreach (self::ACCEPT_ENCODINGS as $acceptEncoding) {
            try {
                $this->messageBus->dispatch(new WarmHttpCacheMessage($acceptEncoding));
            } catch (ExceptionInterface $exception) {
                $this->logger?->error(\sprintf('The HTTP cache warm with Accept-Encoding %s could not be dispatched after the flush; the flush itself succeeded: %s', $acceptEncoding, $exception->getMessage()), ['exception' => $exception]);
            }
        }
    }
}
