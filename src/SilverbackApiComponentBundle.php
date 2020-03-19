<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Silverback\ApiComponentBundle\DependencyInjection\CompilerPass\ApiPlatformCompilerPass;
use Silverback\ApiComponentBundle\DependencyInjection\CompilerPass\DoctrineCompilerPass;
use Silverback\ApiComponentBundle\DependencyInjection\CompilerPass\ImagineCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SilverbackApiComponentBundle extends Bundle
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        if (\class_exists(DoctrineOrmMappingsPass::class)) {
            $container->addCompilerPass(new DoctrineCompilerPass());
        }
        $container->addCompilerPass(new ImagineCompilerPass());
        $container->addCompilerPass(new ApiPlatformCompilerPass());
    }
}
