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

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\DataProvider;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUnpersistedComponent;

class UnpersistedComponentDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = [])
    {
        return new DummyUnpersistedComponent();
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return DummyUnpersistedComponent::class === $resourceClass;
    }
}
