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
use ApiPlatform\Validator\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\HttpCachePurgeStateProcessor;
use Silverback\ApiComponentsBundle\HttpCache\HttpCacheFlusher;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HttpCachePurgeStateProcessorTest extends TestCase
{
    private const string FLUSHER_ID = 'silverback.api_components.http_cache.flusher';
    private const string PURGER_ID = 'silverback.api_components.http_cache.purger';

    public function test_a_request_with_no_body_flushes(): void
    {
        $container = $this->loadContainer();
        $flusher = $this->createMock(HttpCacheFlusher::class);
        $flusher->method('canFlush')->willReturn(true);
        $flusher->expects(self::once())->method('flush');
        $container->set(self::FLUSHER_ID, $flusher);
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->expects(self::never())->method('purgeTags');
        $container->set(self::PURGER_ID, $purger);

        $container->get(HttpCachePurgeStateProcessor::class)->process(null, new Post(), [], ['request' => $this->request('')]);
    }

    public function test_a_body_purges_the_tags_and_the_route_tag_of_each_path_through_the_purger_and_never_flushes(): void
    {
        $container = $this->loadContainer();
        $flusher = $this->createMock(HttpCacheFlusher::class);
        $flusher->expects(self::never())->method('flush');
        $container->set(self::FLUSHER_ID, $flusher);
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->method('canPurgeTags')->willReturn(true);
        $purger->method('getRouteTag')->willReturnCallback(static fn (string $path): string => '/_/routes/' . $path);
        $purger->expects(self::once())->method('purgeTags')->with(['cwa-html', '/_/routes//about']);
        $container->set(self::PURGER_ID, $purger);

        self::assertNull($container->get(HttpCachePurgeStateProcessor::class)->process(null, new Post(), [], ['request' => $this->request('{"paths": ["/about"], "tags": ["cwa-html"]}')]));
    }

    public function test_a_body_is_refused_with_501_when_no_purger_can_purge_tags(): void
    {
        $container = $this->loadContainer();
        $flusher = $this->createMock(HttpCacheFlusher::class);
        $flusher->method('canFlush')->willReturn(true);
        $flusher->expects(self::never())->method('flush');
        $container->set(self::FLUSHER_ID, $flusher);
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->method('canPurgeTags')->willReturn(false);
        $purger->expects(self::never())->method('purgeTags');
        $container->set(self::PURGER_ID, $purger);

        try {
            $container->get(HttpCachePurgeStateProcessor::class)->process(null, new Post(), [], ['request' => $this->request('{"tags": ["cwa-html"]}')]);
            self::fail('The processor should refuse when no purger can purge tags');
        } catch (HttpException $exception) {
            self::assertSame(501, $exception->getStatusCode());
        }
    }

    public function test_a_body_is_refused_with_501_when_there_is_no_purger_service(): void
    {
        $flusher = $this->createMock(HttpCacheFlusher::class);
        $flusher->expects(self::never())->method('flush');

        try {
            (new HttpCachePurgeStateProcessor($flusher))->process(null, new Post(), [], ['request' => $this->request('{"tags": ["cwa-html"]}')]);
            self::fail('The processor should refuse when there is no purger');
        } catch (HttpException $exception) {
            self::assertSame(501, $exception->getStatusCode());
        }
    }

    public function test_an_invalid_body_is_refused_before_the_purger_is_asked_and_never_flushes(): void
    {
        $flusher = $this->createMock(HttpCacheFlusher::class);
        $flusher->method('canFlush')->willReturn(true);
        $flusher->expects(self::never())->method('flush');
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->expects(self::never())->method('canPurgeTags');

        $this->expectException(ValidationException::class);
        (new HttpCachePurgeStateProcessor($flusher, $purger))->process(null, new Post(), [], ['request' => $this->request('{}')]);
    }

    private function request(string $content): Request
    {
        return Request::create('/_/http_cache/purge', 'POST', content: $content);
    }

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
        $container->set(self::PURGER_ID, $this->createStub(HttpCachePurger::class));

        return $container;
    }
}
