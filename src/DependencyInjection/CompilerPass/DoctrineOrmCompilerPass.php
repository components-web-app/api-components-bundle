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

namespace Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Silverback\ApiComponentsBundle\Entity;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class DoctrineOrmCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $modelDir = realpath(\dirname(__DIR__, 2) . '/Resources/config/doctrine-orm');
        $namespace = 'Silverback\ApiComponentsBundle\Entity';
        $mappingPass = DoctrineOrmMappingsPass::createXmlMappingDriver(
            [
                $modelDir => $namespace,
            ],
            ['api_components.orm.manager_name.default'],
            $enabledParameter = false,
            ['ApiComponentsBundle' => $namespace]
        );
        $mappingPass->process($container);

        // Todo: We should be mapping this in with a separate entity manager, or isolate it's UOW
        $imagineModelDir = realpath(\dirname(__DIR__, 2) . '/Resources/config/doctrine-orm-imagine');
        $imagineMappingPass = DoctrineOrmMappingsPass::createXmlMappingDriver(
            [
                $imagineModelDir => 'Silverback\ApiComponentsBundle\Imagine\Entity',
            ],
            ['api_components.orm.manager_name.imagine'],
            'api_component.imagine_enabled',
            []
        );
        $imagineMappingPass->process($container);
    }
}
