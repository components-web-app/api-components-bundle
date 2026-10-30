<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\Page;

final class PageByReferenceStateProvider implements ProviderInterface
{
    public function __construct(private readonly ProviderInterface $decorated, private readonly ManagerRegistry $registry)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $id = $uriVariables['id'] ?? null;
        if (Page::class === $operation->getClass() && \is_string($id)) {
            return $this->registry->getRepository(Page::class)->findOneBy(['reference' => $id]);
        }

        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}
