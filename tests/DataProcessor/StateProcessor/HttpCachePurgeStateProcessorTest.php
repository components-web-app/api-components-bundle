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
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\HttpCachePurgeStateProcessor;
use Silverback\ApiComponentsBundle\HttpCache\HttpCacheFlusher;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HttpCachePurgeStateProcessorTest extends TestCase
{
    private const string FLUSHER_ID = 'silverback.api_components.http_cache.flusher';

    public function test_the_processor_keeps_its_class_name_as_its_service_id_and_is_tagged_as_a_state_processor(): void
    {
        $container = $this->loadContainer();

        self::assertTrue($container->hasDefinition(HttpCachePurgeStateProcessor::class));
        self::assertTrue($container->getDefinition(HttpCachePurgeStateProcessor::class)->hasTag('api_platform.state_processor'));
        self::assertSame(
            HttpCachePurgeStateProcessor::class,
            (string) $container->getAlias('silverback.api_components.api_platform.state_processor.http_cache_purge')
        );
    }

    public function test_the_processor_flushes_the_http_cache(): void
    {
        $container = $this->loadContainer();
        $flusher = $this->createMock(HttpCacheFlusher::class);
        $flusher->method('canFlush')->willReturn(true);
        $flusher->expects(self::once())->method('flush');
        $container->set(self::FLUSHER_ID, $flusher);

        self::assertNull($container->get(HttpCachePurgeStateProcessor::class)->process(null, new Post()));
    }

    public function test_the_processor_refuses_with_501_when_the_purger_has_no_flush(): void
    {
        $container = $this->loadContainer();
        $flusher = $this->createMock(HttpCacheFlusher::class);
        $flusher->method('canFlush')->willReturn(false);
        $flusher->expects(self::never())->method('flush');
        $container->set(self::FLUSHER_ID, $flusher);

        try {
            $container->get(HttpCachePurgeStateProcessor::class)->process(null, new Post());
            self::fail('The processor should refuse when the HTTP cache cannot be flushed');
        } catch (HttpException $exception) {
            self::assertSame(501, $exception->getStatusCode());
        }
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
