<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DependencyInjection\CompilerPass;

use Silverback\ApiComponentBundle\Serializer\MappingLoader\PublishableLoader;
use Silverback\ApiComponentBundle\Serializer\MappingLoader\TimestampedLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
class SerializerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('serializer.mapping.chain_loader');
        $definition->replaceArgument(0, array_merge($definition->getArgument(0), [
            new Reference(PublishableLoader::class),
            new Reference(TimestampedLoader::class),
        ]));
    }
}
