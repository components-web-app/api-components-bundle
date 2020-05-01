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

namespace Silverback\ApiComponentsBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Liip\ImagineBundle\LiipImagineBundle;
use Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass\ApiPlatformCompilerPass;
use Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass\DoctrineCompilerPass;
use Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass\ImagineCompilerPass;
use Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass\SerializerCompilerPass;
use Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass\ValidatorCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use function class_exists;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class SilverbackApiComponentsBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        if (class_exists(DoctrineOrmMappingsPass::class)) {
            $container->addCompilerPass(new DoctrineCompilerPass());
        }
        $container->addCompilerPass(new ApiPlatformCompilerPass());
        $container->addCompilerPass(new SerializerCompilerPass());
        $container->addCompilerPass(new ValidatorCompilerPass());
        if (class_exists(LiipImagineBundle::class)) {
            $container->addCompilerPass(new ImagineCompilerPass());
        }
    }
}
