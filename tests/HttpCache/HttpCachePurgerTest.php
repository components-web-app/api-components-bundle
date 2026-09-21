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
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class HttpCachePurgerTest extends TestCase
{
    private array $purged = [];

    protected function setUp(): void
    {
        $this->purged = [];
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

    private function siteConfigParameter(string $key): SiteConfigParameter
    {
        return (new SiteConfigParameter())->setKey($key)->setValue('value');
    }

    /**
     * @param array<class-string> $purgeRenderedHtmlClasses
     */
    private function createPurger(array $purgeRenderedHtmlClasses): HttpCachePurger
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

        $httpCachePurger = $this->createMock(PurgerInterface::class);
        $httpCachePurger
            ->method('purge')
            ->willReturnCallback(function (array $iris): void {
                $this->purged[] = $iris;
            });

        return new HttpCachePurger($iriConverter, $resourceClassResolver, $httpCachePurger, null, $purgeRenderedHtmlClasses);
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
