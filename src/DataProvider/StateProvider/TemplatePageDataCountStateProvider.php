<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProvider\StateProvider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\Page\TemplatePageDataCounter;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;

/**
 * @implements ProviderInterface<object>
 */
final readonly class TemplatePageDataCountStateProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private ProviderInterface $decorated,
        private TemplatePageDataCounter $counter,
        private PublishableStatusChecker $publishableStatusChecker,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        if (
            is_iterable($data)
            && $operation instanceof CollectionOperationInterface
            && is_a($operation->getClass(), Page::class, true)
            && $this->publishableStatusChecker->isGranted(Page::class)
        ) {
            $this->counter->prefetch($data);
        }

        return $data;
    }
}
