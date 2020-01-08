<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Silverback\ApiComponentBundle\DependencyInjection\CompilerPass\DoctrineCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use function class_exists;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class SilverbackApiComponentBundle extends Bundle
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        if (class_exists(DoctrineOrmMappingsPass::class)) {
            $container->addCompilerPass(new DoctrineCompilerPass());
        }
    }
}
