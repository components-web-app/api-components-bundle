<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\HttpCache;

use ApiPlatform\HttpCache\PurgerInterface;
use ApiPlatform\HttpCache\SouinPurger;
use ApiPlatform\HttpCache\VarnishXKeyPurger;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\Exception\HttpCachePurgeFailedException;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Silverback\ApiComponentsBundle\HttpCache\ManifestKeyResolver;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class HttpCachePurgerTest extends TestCase
{
    private array $purged = [];
    private HttpCachePurgerRecordingLogger $logger;

    protected function setUp(): void
    {
        $this->purged = [];
        $this->logger = new HttpCachePurgerRecordingLogger();
    }

    public function test_the_rendered_html_tag_is_sent_once_however_many_listed_resources_are_written(): void
    {
        $purger = $this->createPurger([SiteConfigParameter::class]);

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->add($this->siteConfigParameter('concatTitle'));
        $purger->add($this->siteConfigParameter('maintenanceModeEnabled'));
        $purger->propagate();

        self::assertCount(1, $this->purged);
        self::assertCount(
            1,
            array_keys($this->purged[0], HttpCachePurger::RENDERED_HTML_TAG, true)
        );
    }

    public function test_the_rendered_html_tag_is_sent_alongside_the_resource_and_collection_iris(): void
    {
        $purger = $this->createPurger([SiteConfigParameter::class]);

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->propagate();

        self::assertSame(
            [
                '/_/siteconfigparameter',
                '/_/siteconfigparameter/siteName',
                HttpCachePurger::RENDERED_HTML_TAG,
            ],
            $this->purged[0]
        );
    }

    public function test_a_write_to_an_unlisted_resource_class_does_not_send_the_rendered_html_tag(): void
    {
        $purger = $this->createPurger([HttpCachePurgerUnrelatedResource::class]);

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->propagate();

        self::assertNotContains(HttpCachePurger::RENDERED_HTML_TAG, $this->purged[0]);
    }

    public function test_a_subclass_of_a_listed_resource_class_sends_the_rendered_html_tag(): void
    {
        $purger = $this->createPurger([HttpCachePurgerListedResource::class]);

        $purger->add(new HttpCachePurgerListedSubclassResource());
        $purger->propagate();

        self::assertContains(HttpCachePurger::RENDERED_HTML_TAG, $this->purged[0]);
    }

    public function test_the_rendered_html_tag_is_not_carried_over_to_the_next_purge(): void
    {
        $purger = $this->createPurger([SiteConfigParameter::class]);

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->propagate();

        $purger->add(new HttpCachePurgerUnrelatedResource());
        $purger->propagate();

        self::assertCount(2, $this->purged);
        self::assertContains(HttpCachePurger::RENDERED_HTML_TAG, $this->purged[0]);
        self::assertNotContains(HttpCachePurger::RENDERED_HTML_TAG, $this->purged[1]);
    }

    public function test_reset_clears_the_rendered_html_tag_before_it_is_ever_purged(): void
    {
        $purger = $this->createPurger([SiteConfigParameter::class]);

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->reset();
        $purger->propagate();

        self::assertSame([], $this->purged);
    }

    public function test_no_purge_is_sent_when_nothing_was_collected(): void
    {
        $purger = $this->createPurger([SiteConfigParameter::class]);

        $purger->propagate();

        self::assertSame([], $this->purged);
    }

    public function test_a_structural_write_collects_a_manifest_grouping_key_for_the_page_it_belongs_to(): void
    {
        $purger = $this->createPurger([]);

        $page = new Page();
        $page->isTemplate = false;
        $componentGroup = new ComponentGroup();
        $componentGroup->pages->add($page);

        $purger->add($componentGroup);
        $purger->propagate();

        self::assertContains(CwaTagCollector::MANIFEST_TAG_PREFIX . '/_/page/id', $this->purged[0]);
    }

    public function test_a_component_content_write_collects_no_manifest_grouping_key(): void
    {
        $purger = $this->createPurger([]);

        $page = new Page();
        $page->isTemplate = false;
        $componentGroup = new ComponentGroup();
        $componentGroup->pages->add($page);

        $component = new DummyComponent();
        $position = new ComponentPosition();
        $position->componentGroup = $componentGroup;
        $component->addComponentPosition($position);

        $purger->add($component);
        $purger->propagate();

        self::assertSame([], array_filter(
            $this->purged[0],
            static fn (string $tag): bool => str_starts_with($tag, CwaTagCollector::MANIFEST_TAG_PREFIX)
        ));
    }

    public function test_purging_the_rendered_html_sends_only_the_rendered_html_tag(): void
    {
        $purger = $this->createPurger([SiteConfigParameter::class]);

        $purger->purgeRenderedHtml();

        self::assertSame([[HttpCachePurger::RENDERED_HTML_TAG]], $this->purged);
    }

    public function test_purging_the_rendered_html_does_not_send_tags_already_collected_in_the_request(): void
    {
        $purger = $this->createPurger([SiteConfigParameter::class]);

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->purgeRenderedHtml();

        self::assertSame([[HttpCachePurger::RENDERED_HTML_TAG]], $this->purged);
    }

    public function test_purging_the_rendered_html_leaves_collected_tags_for_the_next_propagate(): void
    {
        $purger = $this->createPurger([SiteConfigParameter::class]);

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->purgeRenderedHtml();
        $purger->propagate();

        self::assertSame(
            [
                '/_/siteconfigparameter',
                '/_/siteconfigparameter/siteName',
                HttpCachePurger::RENDERED_HTML_TAG,
            ],
            $this->purged[1]
        );
    }

    public function test_purging_the_rendered_html_is_recorded_on_the_collector(): void
    {
        $collectorData = new CwaCollectorData();
        $purger = $this->createPurger([], $collectorData);

        $purger->purgeRenderedHtml();

        self::assertSame([HttpCachePurger::RENDERED_HTML_TAG], $collectorData->getCachePurgedIris());
    }

    public function test_the_service_definition_wires_the_logger_explicitly_and_optionally(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services_doctrine_orm_http_cache_purger.php');

        $arguments = $container->getDefinition('silverback.api_components.http_cache.purger')->getArguments();
        $logger = end($arguments);

        self::assertInstanceOf(Reference::class, $logger);
        self::assertSame('logger', (string) $logger);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $logger->getInvalidBehavior());
    }

    public function test_a_cache_answering_a_purge_with_an_error_status_does_not_fail_the_write(): void
    {
        $purger = $this->createPurgerWith(new VarnishXKeyPurger([$this->invalidationClient(new MockResponse('', ['http_code' => 500]))]));

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->propagate();

        self::assertCount(1, $this->logger->records);
        self::assertInstanceOf(HttpClientExceptionInterface::class, $this->logger->records[0][2]['exception']);
    }

    public function test_an_unreachable_cache_does_not_fail_the_write(): void
    {
        $purger = $this->createPurgerWith(new SouinPurger([$this->invalidationClient(new MockResponse('', ['error' => 'Could not resolve host: souin']))]));

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->propagate();

        self::assertCount(1, $this->logger->records);
        self::assertInstanceOf(TransportException::class, $this->logger->records[0][2]['exception']);
    }

    public function test_a_tag_too_long_for_the_purge_header_does_not_fail_the_write(): void
    {
        $purger = $this->createPurgerWith(new SouinPurger([$this->invalidationClient(new MockResponse('OK'))], 5));

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->propagate();

        self::assertCount(1, $this->logger->records);
        self::assertInstanceOf(\ApiPlatform\Metadata\Exception\RuntimeException::class, $this->logger->records[0][2]['exception']);
    }

    public function test_a_failed_purge_after_a_write_is_logged_as_an_error_with_its_tags_and_exception(): void
    {
        $exception = new TransportException('Could not resolve host: souin');
        $purger = $this->createPurgerWith($this->failingPurger($exception));

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->propagate();

        self::assertCount(1, $this->logger->records);
        [$level, $message, $context] = $this->logger->records[0];
        self::assertSame('error', $level);
        self::assertStringContainsString('/_/siteconfigparameter/siteName', $message);
        self::assertStringContainsString('Could not resolve host: souin', $message);
        self::assertSame(['/_/siteconfigparameter', '/_/siteconfigparameter/siteName'], $context['tags']);
        self::assertSame($exception, $context['exception']);
    }

    public function test_a_failed_purge_after_a_write_without_a_logger_does_not_throw(): void
    {
        $purger = $this->createPurgerWith($this->failingPurger(new TransportException('Could not resolve host: souin')), withLogger: false);

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->propagate();

        $this->expectNotToPerformAssertions();
    }

    public function test_the_tags_of_a_failed_purge_are_not_carried_over_to_the_next_purge(): void
    {
        $sent = [];
        $httpCachePurger = $this->createStub(PurgerInterface::class);
        $httpCachePurger
            ->method('purge')
            ->willReturnCallback(static function (array $iris) use (&$sent): void {
                $sent[] = $iris;
                if (1 === \count($sent)) {
                    throw new TransportException('Could not resolve host: souin');
                }
            });
        $purger = $this->createPurgerWith($httpCachePurger);

        $purger->add($this->siteConfigParameter('siteName'));
        $purger->propagate();
        $purger->add($this->siteConfigParameter('concatTitle'));
        $purger->propagate();

        self::assertSame(['/_/siteconfigparameter', '/_/siteconfigparameter/concatTitle'], $sent[1]);
    }

    public function test_an_exception_that_is_not_a_cache_failure_is_not_swallowed_after_a_write(): void
    {
        $purger = $this->createPurgerWith($this->failingPurger(new \LogicException('A programming error')));

        $purger->add($this->siteConfigParameter('siteName'));

        $this->expectException(\LogicException::class);
        $purger->propagate();
    }

    public function test_purging_the_rendered_html_fails_loudly_when_the_cache_answers_with_an_error_status(): void
    {
        $purger = $this->createPurgerWith(new SouinPurger([$this->invalidationClient(new MockResponse('', ['http_code' => 502]))]));

        try {
            $purger->purgeRenderedHtml();
            self::fail('A failed purge of the rendered HTML must throw.');
        } catch (HttpCachePurgeFailedException $exception) {
            self::assertStringContainsString(HttpCachePurger::RENDERED_HTML_TAG, $exception->getMessage());
            self::assertInstanceOf(HttpClientExceptionInterface::class, $exception->getPrevious());
        }
        self::assertSame([], $this->logger->records);
    }

    public function test_purging_the_rendered_html_fails_loudly_when_the_cache_cannot_be_reached(): void
    {
        $failure = new TransportException('Could not resolve host: souin');
        $purger = $this->createPurgerWith($this->failingPurger($failure));

        try {
            $purger->purgeRenderedHtml();
            self::fail('A failed purge of the rendered HTML must throw.');
        } catch (HttpCachePurgeFailedException $exception) {
            self::assertStringContainsString('Could not resolve host: souin', $exception->getMessage());
            self::assertSame($failure, $exception->getPrevious());
        }
    }

    public function test_purging_the_rendered_html_does_not_swallow_an_exception_that_is_not_a_cache_failure(): void
    {
        $purger = $this->createPurgerWith($this->failingPurger(new \LogicException('A programming error')));

        $this->expectException(\LogicException::class);
        $purger->purgeRenderedHtml();
    }

    private function invalidationClient(MockResponse $response): ScopingHttpClient
    {
        return ScopingHttpClient::forBaseUri(new MockHttpClient($response), 'http://cache/souin-api/souin');
    }

    private function failingPurger(\Throwable $exception): PurgerInterface
    {
        $httpCachePurger = $this->createStub(PurgerInterface::class);
        $httpCachePurger->method('purge')->willThrowException($exception);

        return $httpCachePurger;
    }

    private function siteConfigParameter(string $key): SiteConfigParameter
    {
        return (new SiteConfigParameter())->setKey($key)->setValue('value');
    }

    /**
     * @param array<class-string> $purgeRenderedHtmlClasses
     */
    private function createPurger(array $purgeRenderedHtmlClasses, ?CwaCollectorData $collectorData = null): HttpCachePurger
    {
        $httpCachePurger = $this->createStub(PurgerInterface::class);
        $httpCachePurger
            ->method('purge')
            ->willReturnCallback(function (array $iris): void {
                $this->purged[] = $iris;
            });

        return $this->createPurgerWith($httpCachePurger, $purgeRenderedHtmlClasses, $collectorData);
    }

    /**
     * @param array<class-string> $purgeRenderedHtmlClasses
     */
    private function createPurgerWith(PurgerInterface $httpCachePurger, array $purgeRenderedHtmlClasses = [], ?CwaCollectorData $collectorData = null, bool $withLogger = true): HttpCachePurger
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter
            ->method('getIriFromResource')
            ->willReturnCallback(static function (object|string $resource, int $referenceType = 0, ?Operation $operation = null): string {
                $class = \is_string($resource) ? $resource : $resource::class;
                $collectionIri = \sprintf('/_/%s', strtolower((new \ReflectionClass($class))->getShortName()));

                if (null !== $operation) {
                    return $collectionIri;
                }

                return \sprintf('%s/%s', $collectionIri, $resource instanceof SiteConfigParameter ? $resource->getKey() : 'id');
            });

        $resourceClassResolver = $this->createStub(ResourceClassResolverInterface::class);
        $resourceClassResolver
            ->method('getResourceClass')
            ->willReturnCallback(static fn (object $value): string => $value::class);

        return new HttpCachePurger($iriConverter, $resourceClassResolver, $httpCachePurger, $collectorData, $purgeRenderedHtmlClasses, new ManifestKeyResolver($iriConverter), $withLogger ? $this->logger : null);
    }
}

class HttpCachePurgerRecordingLogger extends AbstractLogger
{
    /** @var array<int, array{0: mixed, 1: string, 2: array}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
}

class HttpCachePurgerListedResource
{
}

class HttpCachePurgerListedSubclassResource extends HttpCachePurgerListedResource
{
}

class HttpCachePurgerUnrelatedResource
{
}
