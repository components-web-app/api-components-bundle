<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;
use Symfony\Component\HttpFoundation\Request;

final class WorkerModeMemoryTest extends FunctionalTestCase
{
    use SiteTrait;

    public function test_the_skolem_iri_map_and_the_resource_metadata_map_are_empty_after_each_requests_services_reset(): void
    {
        static::bootKernel();
        self::createPageDataWithPublishedComponentInAPageDataPropertyPosition('/worker');
        $kernel = static::$kernel;
        $container = $kernel->getContainer();
        $skolem = static::getContainer()->get('api_platform.symfony.iri_converter.skolem');
        $provider = static::getContainer()->get('silverback.api_components.serializer.resource_metadata_provider');
        $sizes = static fn (): array => [
            'skolem' => \count((new \ReflectionProperty($skolem, 'objectHashMap'))->getValue($skolem)),
            'metadata' => \count((new \ReflectionProperty($provider, 'metadatas'))->getValue($provider)),
        ];
        self::getEntityManager()->clear();

        foreach (['/_/routes//worker', '/_/resource_manifest//worker', '/_/routes', '/_/routes//worker'] as $url) {
            $status = $kernel->handle(Request::create($url, 'GET', server: ['HTTP_ACCEPT' => 'application/ld+json']))->getStatusCode();
            $during = $sizes();
            self::assertSame(200, $status, $url);
            self::assertGreaterThan(0, $during['skolem'], \sprintf('%s left no skolem entries.', $url));
            self::assertGreaterThan(0, $during['metadata'], \sprintf('%s left no metadata entries.', $url));

            $container->get('services_resetter')->reset();

            self::assertSame(['skolem' => 0, 'metadata' => 0], $sizes(), \sprintf('Entries remain after a services reset following %s.', $url));
        }
    }

    private static function createPageDataWithPublishedComponentInAPageDataPropertyPosition(string $path): void
    {
        $group = self::newComponentGroup('test');
        $position = new ComponentPosition();
        $position->pageDataProperty = 'publishableComponent';
        $position->pageDataClass = PageDataWithComponent::class;
        $position->componentGroup = $group;
        $position->sortValue = 0;
        $page = self::newPage('test page', true);
        $page->addComponentGroup($group);
        $component = new DummyPublishableComponent();
        $component->setPublishedAt(new \DateTime());
        $pageData = new PageDataWithComponent();
        $pageData->publishableComponent = $component;
        $pageData->page = $page;
        $route = self::newRoute($path)->setPageData($pageData);
        $manager = self::getEntityManager();
        $timestamped = static::getContainer()->get(TimestampedDataPersister::class);
        foreach ([$group, $position, $page, $component, $pageData, $route] as $entity) {
            if ($timestamped->isConfigured($entity)) {
                $timestamped->persistTimestampedFields($entity, true);
            }
            $manager->persist($entity);
        }
        $manager->flush();
    }
}
