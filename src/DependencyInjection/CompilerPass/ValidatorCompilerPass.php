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

use Silverback\ApiComponentBundle\Validator\MappingLoader\UploadsLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class ValidatorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $container
            ->getDefinition('validator.builder')
            ->addMethodCall('addLoader', [new Reference(UploadsLoader::class)]);
    }
}
