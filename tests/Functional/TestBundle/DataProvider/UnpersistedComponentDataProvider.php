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

use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUnpersistedComponent;

class UnpersistedComponentDataProvider implements ProviderInterface
{
    public function provide(string $resourceClass, array $uriVariables = [], ?string $operationName = null, array $context = [])
    {
        return new DummyUnpersistedComponent();
    }

    public function supports(string $resourceClass, array $uriVariables = [], ?string $operationName = null, array $context = []): bool
    {
        return DummyUnpersistedComponent::class === $resourceClass;
    }
}
