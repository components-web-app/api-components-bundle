<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Identifier;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithParentTypedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class UnresolvableRelationIriTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use ResourceTrait;
    use SiteTrait;

    /**
     * @return iterable<string, array{string}>
     */
    public static function unresolvableIris(): iterable
    {
        yield 'a placeholder that is not a path' => ['__new__'];
        yield 'a path no route matches' => ['/no/such/route'];
        yield 'a path whose route is not an API resource' => ['/_/health'];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function relations(): iterable
    {
        yield 'a component position component, typed abstract' => ['position', 'POST', 'component'];
        yield 'a component position group, typed concrete' => ['position', 'POST', 'componentGroup'];
        yield 'a route page data, typed abstract' => ['route', 'POST', 'pageData'];
        yield 'a route page, typed concrete' => ['route', 'POST', 'page'];
        yield 'a page data parent page data, typed abstract' => ['pageData', 'PATCH', 'parentPageData'];
        yield 'a page data component property, typed abstract' => ['pageData', 'PATCH', 'component'];
        yield 'a page data template page, typed concrete' => ['pageData', 'PATCH', 'page'];
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function relationsWithUnresolvableIris(): iterable
    {
        foreach (self::relations() as $relation => $relationArguments) {
            foreach (self::unresolvableIris() as $iri => $iriArguments) {
                yield $relation . ' given ' . $iri => [...$relationArguments, ...$iriArguments];
            }
        }
    }

    #[DataProvider('relationsWithUnresolvableIris')]
    public function test_a_relation_given_an_iri_that_matches_no_resource_route_is_refused_as_a_missing_item_is(string $resource, string $method, string $property, string $iri): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$url, $body] = self::request($resource);
        $missingItemStatus = self::requestJsonLd($client, $method, $url, [$property => self::missingItemIri($property)] + $body)->getStatusCode();

        $response = self::requestJsonLd($client, $method, $url, [$property => $iri] + $body);

        self::assertContains($missingItemStatus, [400, 422]);
        self::assertSame($missingItemStatus, $response->getStatusCode());
    }

    /**
     * @return array{string, array<string, string>}
     */
    private static function request(string $resource): array
    {
        return match ($resource) {
            'position' => ['/_/component_positions', ['componentGroup' => self::iriOf(self::createComponentGroupWithComponents(0)['group']), 'component' => self::iriOf(self::createComponent())]],
            'route' => ['/_/routes', ['path' => '/unresolvable', 'name' => 'unresolvable']],
            'pageData' => [self::iriOf(self::createPageData()), []],
        };
    }

    private static function missingItemIri(string $property): string
    {
        $component = self::createComponent();
        $iri = self::iriOf($component);
        $replacement = match ($property) {
            'component' => '/component/dummy_components/',
            'componentGroup' => '/_/component_groups/',
            'pageData', 'parentPageData' => '/page_data/page_data_with_parent_typed_components/',
            'page' => '/_/pages/',
        };
        $manager = static::getEntityManager();
        $manager->getConnection()->delete($manager->getClassMetadata(AbstractComponent::class)->getTableName(), ['id' => (string) $component->getId()]);

        return $replacement . substr($iri, (int) strrpos($iri, '/') + 1);
    }

    private static function createComponent(): DummyComponent
    {
        $component = new DummyComponent();
        static::persistAndFlush($component);

        return $component;
    }

    private static function createPageData(): PageDataWithParentTypedComponent
    {
        $pageData = new PageDataWithParentTypedComponent();
        $pageData->page = self::newPage('unresolvable-template', true);
        static::persistAndFlush($pageData->page, $pageData);

        return $pageData;
    }
}
