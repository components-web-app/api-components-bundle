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
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\DraftComponentReferenceListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class DraftComponentReferenceListenerServiceTest extends TestCase
{
    public function test_the_listener_runs_on_flush_before_the_listeners_that_propagate_the_write(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services.php');

        $definition = $container->getDefinition('silverback.api_components.doctrine.event_listener.draft_component_reference');

        self::assertSame(DraftComponentReferenceListener::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertEquals([new Reference(PublishableAttributeReader::class)], $definition->getArguments());
        self::assertSame([['event' => 'onFlush', 'priority' => 100]], $definition->getTag('doctrine.event_listener'));
        self::assertSame([['event' => 'onFlush'], ['event' => 'postFlush']], $container->getDefinition('silverback.api_components.event_listener.doctrine.propagate_updates_listener')->getTag('doctrine.event_listener'));
        self::assertSame(
            'silverback.api_components.doctrine.event_listener.draft_component_reference',
            (string) $container->getAlias(DraftComponentReferenceListener::class)
        );
    }
}
