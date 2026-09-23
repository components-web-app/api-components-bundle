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
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
class HttpCachePurgeStateProcessor implements ProcessorInterface
{
    public function __construct(private readonly HttpCacheFlusher $httpCacheFlusher)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$this->httpCacheFlusher->canFlush()) {
            throw new HttpException(501, 'The configured HTTP cache purger cannot flush the whole cache.');
        }

        $this->httpCacheFlusher->flush();

        return null;
    }
}
