<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Silverback\ApiComponentsBundle\HttpCache\HttpCacheFlusher;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Silverback\ApiComponentsBundle\HttpCache\ScopedPurgeRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
class HttpCachePurgeStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly HttpCacheFlusher $httpCacheFlusher,
        private readonly ?HttpCachePurger $httpCachePurger = null,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $request = $context['request'] ?? null;
        $scope = ScopedPurgeRequest::fromContent($request instanceof Request ? $request->getContent() : '');

        if (null === $scope) {
            $this->flush();

            return null;
        }

        if (null === $this->httpCachePurger || !$this->httpCachePurger->canPurgeTags()) {
            throw new HttpException(501, 'No HTTP cache purger is configured, so nothing can be purged.');
        }

        $this->httpCachePurger->purgeTags($scope->resolveTags($this->httpCachePurger->getRouteTag(...)));

        return null;
    }

    private function flush(): void
    {
        if (!$this->httpCacheFlusher->canFlush()) {
            throw new HttpException(501, 'The configured HTTP cache purger cannot flush the whole cache.');
        }

        $this->httpCacheFlusher->flush();
    }
}
