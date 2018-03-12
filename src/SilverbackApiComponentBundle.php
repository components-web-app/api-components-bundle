<?php

namespace Silverback\ApiComponentBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
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
        $this->addRegisterMappingsPass($container);
        $container->addCompilerPass(new ImagineCompilerPass());
    }

    /**
     * @param ContainerBuilder $container
     */
    private function addRegisterMappingsPass(ContainerBuilder $container): void
    {
        if (\class_exists(DoctrineOrmMappingsPass::class)) {
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAnnotationMappingDriver([__NAMESPACE__ . '\\Entity'], [__DIR__ . '/Entity']));
        }
    }
}
