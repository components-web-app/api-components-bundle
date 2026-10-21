<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Contracts\Service\ResetInterface;

class ResetInterfaceServicesTest extends TestCase
{
    private const array CONFIG_FILES = [
        'services.php',
        'services_normalizers.php',
        'services_doctrine_orm_http_cache_purger.php',
        'services_doctrine_orm_mercure_publisher.php',
        'services_orphaned_resources.php',
        'services_orphaned_files.php',
        'services_realtime.php',
        'services_maker.php',
    ];

    public function test_every_bundle_service_that_implements_reset_interface_is_tagged_kernel_reset(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config'));
        foreach (self::CONFIG_FILES as $file) {
            $loader->load($file);
        }

        $checked = 0;
        $untagged = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass() ?? $id;
            if ($definition->isAbstract() || !class_exists($class) || !is_subclass_of($class, ResetInterface::class)) {
                continue;
            }
            ++$checked;
            if (!$definition->hasTag('kernel.reset') && !$definition->hasTag('data_collector')) {
                $untagged[] = \sprintf('%s (%s)', $id, $class);
            }
        }

        self::assertGreaterThan(5, $checked);
        self::assertSame([], $untagged, 'These services implement ResetInterface but are not tagged kernel.reset, so worker mode never resets them.');
    }
}
