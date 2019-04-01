<?php

namespace Silverback\ApiComponentBundle\DependencyInjection\CompilerPass;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DoctrineCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        DoctrineOrmMappingsPass::createAnnotationMappingDriver([__NAMESPACE__ . '\\Entity'], [__DIR__ . '/Entity']);
    }
}
