<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\Post;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\RenderedHtmlPurgeStateProcessor;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class RenderedHtmlPurgeStateProcessorTest extends TestCase
{
    private const string PURGER_ID = 'silverback.api_components.http_cache.purger';

    public function test_the_processor_keeps_its_class_name_as_its_service_id_and_is_tagged_as_a_state_processor(): void
    {
        $container = $this->loadContainer();

        self::assertTrue($container->hasDefinition(RenderedHtmlPurgeStateProcessor::class));
        self::assertTrue($container->getDefinition(RenderedHtmlPurgeStateProcessor::class)->hasTag('api_platform.state_processor'));
        self::assertSame(
            RenderedHtmlPurgeStateProcessor::class,
            (string) $container->getAlias('silverback.api_components.api_platform.state_processor.rendered_html_purge')
        );
    }

    public function test_the_processor_built_from_its_service_definition_purges_the_rendered_html(): void
    {
        $container = $this->loadContainer();
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->expects(self::once())->method('purgeRenderedHtml');
        $purger->expects(self::never())->method('propagate');
        $container->set(self::PURGER_ID, $purger);

        $processor = $container->get(RenderedHtmlPurgeStateProcessor::class);

        self::assertNull($processor->process(null, new Post()));
    }

    public function test_the_processor_does_nothing_when_no_http_cache_purger_is_configured(): void
    {
        $container = $this->loadContainer();
        $container->removeDefinition(self::PURGER_ID);

        self::assertNull($container->get(RenderedHtmlPurgeStateProcessor::class)->process(null, new Post()));
    }

    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../src/Resources/config'));
        $loader->load('services_doctrine_orm_http_cache_purger.php');
        (new ResolveClassPass())->process($container);

        return $container;
    }
}
