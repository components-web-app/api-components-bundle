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

use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithParentTypedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class PageDataParentTypedComponentTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use ConsoleTrait;
    use JsonTrait;
    use MercureTrait;
    use SiteTrait;

    public function test_the_components_usage_counts_the_page_data_property(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createComponentHeldByAParentTypedPageDataProperty();

        self::requestJsonLd($client, 'GET', self::iriOf($site['component']) . '/usage');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('positionCount', 0);
        self::assertJsonNodeSame('pageDataCount', 1);
        self::assertJsonNodeSame('total', 1);
    }

    public function test_the_clean_orphaned_command_keeps_the_component(): void
    {
        self::bootKernel();
        $site = self::createComponentHeldByAParentTypedPageDataProperty();

        $tester = self::runConsoleCommand('silverback:api-components:clean-orphaned');

        self::assertSame(0, $tester->getStatusCode());
        self::assertResourceExists(self::iriOf($site['component']));
        $pageData = self::findByIri(self::iriOf($site['pageData']));
        self::assertInstanceOf(PageDataWithParentTypedComponent::class, $pageData);
        self::assertNotNull($pageData->component);
        self::assertSame(self::iriOf($site['component']), self::iriOf($pageData->component));
    }

    public function test_the_component_is_public_when_the_page_data_is_on_a_live_route(): void
    {
        $client = static::createClient();
        $site = self::createComponentHeldByAParentTypedPageDataProperty('/parent-typed');

        self::requestJsonLd($client, 'GET', self::iriOf($site['component']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_component_is_not_public_when_the_page_data_has_no_route(): void
    {
        $client = static::createClient();
        $site = self::createComponentHeldByAParentTypedPageDataProperty();

        self::requestJsonLd($client, 'GET', self::iriOf($site['component']));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_the_component_is_not_public_when_the_page_datas_route_is_not_live_yet(): void
    {
        $client = static::createClient();
        $site = self::createComponentHeldByAParentTypedPageDataProperty('/parent-typed');
        self::setRouteLiveAt('/parent-typed', new \DateTimeImmutable('2999-01-01T00:00:00+00:00'));

        self::requestJsonLd($client, 'GET', self::iriOf($site['component']));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_editing_the_component_purges_the_page_data_and_publishes_it_to_mercure(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createComponentHeldByAParentTypedPageDataProperty('/parent-typed');
        HubStub::clearPublished();

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['component']), '{"uiComponent": "Changed"}');

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged(self::iriOf($site['pageData']));
        self::assertMercureUpdatePublishedFor(self::iriOf($site['pageData']));
    }

    /**
     * @return array{component: DummyComponent, pageData: PageDataWithParentTypedComponent}
     */
    private static function createComponentHeldByAParentTypedPageDataProperty(?string $path = null): array
    {
        $page = self::newPage('parent typed page', true);
        $component = new DummyComponent();
        self::persistAndFlush($page, $component);
        $manager = self::getEntityManager();
        $pageData = new PageDataWithParentTypedComponent();
        $pageData->component = $manager->getReference(DummyComponent::class, $component->getId());
        $pageData->page = $manager->getReference(Page::class, $page->getId());
        $entities = [$pageData];
        if (null !== $path) {
            $entities[] = self::newRoute($path)->setPageData($pageData);
        }
        self::persistAndFlush(...$entities);

        return ['component' => $component, 'pageData' => $pageData];
    }
}
