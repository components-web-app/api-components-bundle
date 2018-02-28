<?php

namespace Silverback\ApiComponentBundle\DependencyInjection\CompilerPass;

use Silverback\ApiComponentBundle\Imagine\FileSystemLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ImagineFileSystemOverride implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition('liip_imagine.binary.loader.prototype.filesystem');
        $definition->setClass(FileSystemLoader::class);
    }
}
