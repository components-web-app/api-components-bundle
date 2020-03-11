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

use Silverback\ApiComponentBundle\File\FileUploader;
use Silverback\ApiComponentBundle\Imagine\PathResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class ImagineCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        try {
            $rootPaths = $container->getDefinition('liip_imagine.binary.loader.default')->getArgument(2)->getArgument(0);

            $container->getDefinition(PathResolver::class)
                ->setArgument('$roots', $rootPaths);
            $container->getDefinition(FileUploader::class)
                ->setArgument('$rootPaths', $rootPaths);
        } catch (ServiceNotFoundException $e) {
        }
    }
}
