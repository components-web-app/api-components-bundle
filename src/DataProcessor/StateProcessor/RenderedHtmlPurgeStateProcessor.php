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
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;

/**
 * @implements ProcessorInterface<mixed, null>
 */
class RenderedHtmlPurgeStateProcessor implements ProcessorInterface
{
    public function __construct(private readonly ?HttpCachePurger $httpCachePurger)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->httpCachePurger?->purgeRenderedHtml();

        return null;
    }
}
