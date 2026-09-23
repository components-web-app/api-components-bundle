<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\HttpCache;

use ApiPlatform\HttpCache\PurgerInterface;
use ApiPlatform\HttpCache\SouinPurger;
use Silverback\ApiComponentsBundle\Exception\HttpCacheFlushFailedException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpCacheFlusher
{
    /**
     * @param list<array{url: string, client: HttpClientInterface}> $invalidationClients
     */
    public function __construct(
        private readonly ?PurgerInterface $purger,
        private readonly array $invalidationClients = [],
    ) {
    }

    public function canFlush(): bool
    {
        return $this->purger instanceof SouinPurger && [] !== $this->invalidationClients;
    }

    public function flush(): void
    {
        if (!$this->canFlush()) {
            return;
        }

        foreach ($this->invalidationClients as ['url' => $url, 'client' => $client]) {
            $flushUrl = rtrim($url, '/') . '/flush';
            try {
                $statusCode = $client->request('PURGE', $flushUrl)->getStatusCode();
            } catch (ExceptionInterface $exception) {
                throw new HttpCacheFlushFailedException(\sprintf('Failed to flush the HTTP cache at %s: %s', $flushUrl, $exception->getMessage()), previous: $exception);
            }
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new HttpCacheFlushFailedException(\sprintf('Failed to flush the HTTP cache at %s: the cache responded with %d', $flushUrl, $statusCode));
            }
        }
    }
}
