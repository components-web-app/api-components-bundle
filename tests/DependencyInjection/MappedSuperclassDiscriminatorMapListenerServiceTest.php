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
use Silverback\ApiComponentsBundle\EventListener\Doctrine\MappedSuperclassDiscriminatorMapListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class MappedSuperclassDiscriminatorMapListenerServiceTest extends TestCase
{
    public function test_the_listener_is_registered_for_load_class_metadata_without_autoconfiguration(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services.php');

        $definition = $container->getDefinition('silverback.api_components.doctrine.event_listener.mapped_superclass_discriminator_map');

        self::assertSame(MappedSuperclassDiscriminatorMapListener::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame(
            [['event' => 'loadClassMetadata', 'method' => 'loadClassMetadata']],
            $definition->getTag('doctrine.event_listener')
        );
        self::assertSame(
            'silverback.api_components.doctrine.event_listener.mapped_superclass_discriminator_map',
            (string) $container->getAlias(MappedSuperclassDiscriminatorMapListener::class)
        );
    }
}
