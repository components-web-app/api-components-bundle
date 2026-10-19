<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Component;

use ApiPlatform\Test\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Metadata\ComponentUsageMetadata;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;

final class ComponentUsageTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use ComponentSiteTrait;

    public function test_the_usage_counts_positions_in_different_groups(): void
    {
        $client = self::adminClient();
        $group = self::createGroupWithComponentIris(1);
        $component = $group['components'][0];
        $other = self::createGroupWithComponentIris(0, 'other_collection');
        self::addPositionTo($other['group'], $component, 0);
        self::addPositionTo($other['group'], $component, 1);

        self::requestJsonLd($client, 'GET', $component . '/usage');

        self::assertUsage(3, 0);
    }

    public function test_the_usage_adds_the_page_data_properties_holding_the_component(): void
    {
        $client = self::adminClient();
        $group = self::createGroupWithComponentIris(1);
        $component = $group['components'][0];
        $other = self::createGroupWithComponentIris(0, 'other_collection');
        self::addPositionTo($other['group'], $component, 0);
        self::holdInPageData($component);
        self::holdInPageData($component);

        self::requestJsonLd($client, 'GET', $component . '/usage');

        self::assertUsage(2, 2);
    }

    public function test_the_usage_is_the_request_data_for_the_listeners_that_read_it(): void
    {
        $client = self::adminClient();
        $group = self::createGroupWithComponentIris(1);
        $component = $group['components'][0];

        self::requestJsonLd($client, 'GET', $component . '/usage');

        self::assertUsage(1, 0);
        $data = $client->getKernelBrowser()->getRequest()->attributes->get('data');
        self::assertInstanceOf(ComponentUsageMetadata::class, $data);
        self::assertSame(1, $data->getTotal());
    }

    #[DataProvider('publishedOrDraft')]
    public function test_a_published_component_and_its_draft_give_the_same_usage(string $requested): void
    {
        $client = self::adminClient();
        $group = self::createGroupWithComponentIris(0);
        $pair = self::addPublishedComponentWithDraftTo($group['group']);
        $other = self::createGroupWithComponentIris(0, 'other_collection');
        self::addPositionTo($other['group'], $pair['published'], 0);
        self::holdInPageData($pair['published']);

        self::requestJsonLd($client, 'GET', $pair[$requested] . '/usage');

        self::assertUsage(2, 1);
    }

    #[DataProvider('publishedOrDraft')]
    public function test_a_position_given_the_draft_holds_the_published_component_and_is_counted(string $requested): void
    {
        $client = self::adminClient();
        $group = self::createGroupWithComponentIris(0);
        $pair = self::addPublishedComponentWithDraftTo($group['group']);
        $other = self::createGroupWithComponentIris(0, 'other_collection');
        self::addPositionTo($other['group'], $pair['draft'], 0);

        self::requestJsonLd($client, 'GET', $pair[$requested] . '/usage');

        self::assertUsage(2, 0);
    }

    public static function publishedOrDraft(): iterable
    {
        yield 'the published component' => ['published'];
        yield 'the draft' => ['draft'];
    }

    public function test_the_usage_is_as_readable_as_the_component_so_an_anonymous_caller_reads_it_for_a_component_on_a_live_route(): void
    {
        $client = static::createClient();
        $site = self::createComponentInRoute('/live');

        self::requestJsonLd($client, 'GET', self::iriOf($site['component']) . '/usage');

        self::assertUsage(1, 0);
    }

    public function test_an_anonymous_caller_cannot_read_the_usage_of_a_component_on_a_page_no_route_reaches(): void
    {
        $client = static::createClient();
        $component = self::createComponentOnAnUnroutedPage();

        self::requestJsonLd($client, 'GET', $component . '/usage');

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_user_who_is_not_an_admin_cannot_read_the_usage_of_a_component_on_a_page_no_route_reaches(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $component = self::createComponentOnAnUnroutedPage();

        self::requestJsonLd($client, 'GET', $component . '/usage');

        self::assertResponseStatusCodeSame(403);
    }

    public function test_a_user_who_is_not_an_admin_cannot_read_the_usage_of_a_draft(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(0);
        $pair = self::addPublishedComponentWithDraftTo($group['group']);

        self::requestJsonLd($client, 'GET', $pair['draft'] . '/usage');

        self::assertResponseStatusCodeSame(404);
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        return $client;
    }

    private static function createComponentOnAnUnroutedPage(): string
    {
        $page = self::newPage('unrouted');
        $created = self::createComponentGroupWithComponents(1);
        $group = self::getEntityManager()->find(ComponentGroup::class, $created['group']->getId());
        self::assertInstanceOf(ComponentGroup::class, $group);
        $page->addComponentGroup($group);
        self::persistAndFlush($page);

        return self::iriOf($created['components'][0]);
    }

    private static function holdInPageData(string $componentIri): void
    {
        $page = self::newPage('template ' . self::countEntities(PageDataWithComponent::class), true);
        $component = self::findByIri($componentIri);
        $pageData = new PageDataWithComponent();
        $pageData->page = $page;
        if ($component instanceof DummyPublishableComponent) {
            $pageData->publishableComponent = $component;
        } else {
            self::assertInstanceOf(DummyComponent::class, $component);
            $pageData->component = $component;
        }
        self::persistAndFlush($page, $pageData);
    }

    private static function assertUsage(int $positionCount, int $pageDataCount): void
    {
        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('positionCount', $positionCount);
        self::assertJsonNodeSame('pageDataCount', $pageDataCount);
        self::assertJsonNodeSame('total', $positionCount + $pageDataCount);
    }
}
