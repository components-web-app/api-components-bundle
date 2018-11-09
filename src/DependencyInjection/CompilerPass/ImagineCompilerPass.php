<?php

namespace Silverback\ApiComponentBundle\DependencyInjection\CompilerPass;

use Silverback\ApiComponentBundle\Imagine\PathResolver;
use Silverback\ApiComponentBundle\Uploader\FileUploader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ImagineCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $rootPaths = $container->getDefinition('liip_imagine.binary.loader.default')->getArgument(2)->getArgument(0);

        $container->getDefinition(PathResolver::class)
            ->setArgument(0, $rootPaths);
        $container->getDefinition(FileUploader::class)
            ->setArgument(2, $rootPaths);
    }
}
