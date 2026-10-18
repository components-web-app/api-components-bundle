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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait ComponentSiteTrait
{
    use ApiRequestTrait;
    use JsonTrait;
    use MercureTrait;
    use SiteTrait;

    /**
     * @return array{group: string, components: list<string>, positions: list<string>}
     */
    protected static function createGroupWithComponentIris(int $count, string $reference = 'collection'): array
    {
        $created = static::createComponentGroupWithComponents($count, $reference);

        return [
            'group' => static::iriOf($created['group']),
            'components' => array_map(static fn (DummyComponent $component): string => static::iriOf($component), $created['components']),
            'positions' => array_map(static fn (ComponentPosition $position): string => static::iriOf($position), $created['positions']),
        ];
    }

    protected static function createDummyComponent(): string
    {
        $component = new DummyComponent();
        static::persistAndFlush($component);

        return static::iriOf($component);
    }

    /**
     * @return array<string, string>
     */
    protected static function createGroupWithPositions(string $positions, string $reference = 'collection'): array
    {
        $group = static::newComponentGroup($reference);
        $created = [];
        $entities = [$group];
        foreach (static::parsePositions($positions) as [$name, $sortValue]) {
            $component = new DummyComponent();
            $position = static::newComponentPosition($group, $component, $sortValue);
            $created[] = [$name, $position];
            array_push($entities, $component, $position);
        }
        static::persistAndFlush(...$entities);

        $byValue = [];
        foreach ($created as [, $position]) {
            $byValue[$position->sortValue][] = $position;
        }
        foreach ($byValue as $sortValue => $tied) {
            usort($tied, static fn (ComponentPosition $a, ComponentPosition $b): int => strcmp((string) $a->getId(), (string) $b->getId()));
            $byValue[$sortValue] = $tied;
        }
        $names = ['group' => static::iriOf($group)];
        foreach ($created as [$name, $position]) {
            $names[$name] = static::iriOf(array_shift($byValue[$position->sortValue]));
        }

        return $names;
    }

    /**
     * @param array<string, string> $names
     */
    protected static function assertGroupHoldsPositions(string $groupIri, string $positions, array $names): void
    {
        $group = static::findByIri($groupIri);
        self::assertInstanceOf(ComponentGroup::class, $group);
        $labels = array_flip($names);
        $actual = [];
        foreach (static::getEntityManager()->getRepository(ComponentPosition::class)->findBy(['componentGroup' => $group], ['sortValue' => 'ASC', 'id' => 'ASC']) as $position) {
            $iri = static::iriOf($position);
            $actual[] = ($labels[$iri] ?? $iri) . $position->sortValue;
        }
        $expected = array_map(static fn (array $position): string => $position[0] . $position[1], static::parsePositions($positions));
        self::assertSame($expected, $actual);
    }

    /**
     * @param array<string, int> $expected
     */
    protected static function assertSortValues(array $expected): void
    {
        $actual = [];
        foreach (array_keys($expected) as $iri) {
            $position = static::findByIri($iri);
            self::assertInstanceOf(ComponentPosition::class, $position);
            $actual[$iri] = $position->sortValue;
        }
        self::assertSame($expected, $actual);
    }

    /**
     * @param \Closure(object): void $change
     */
    protected static function updateResource(string $iri, \Closure $change): void
    {
        $resource = static::findByIri($iri);
        self::assertNotNull($resource, \sprintf('The resource %s cannot be found.', $iri));
        $change($resource);
        static::getEntityManager()->flush();
        static::rebootKernel();
    }

    protected static function setMaxComponents(string $groupIri, int $maxComponents): void
    {
        static::updateResource($groupIri, static function (object $group) use ($maxComponents): void {
            \assert($group instanceof ComponentGroup);
            $group->maxComponents = $maxComponents;
        });
    }

    protected static function setAllowedComponent(string $groupIri, string $allowedComponent): void
    {
        static::updateResource($groupIri, static function (object $group) use ($allowedComponent): void {
            \assert($group instanceof ComponentGroup);
            if ('' !== $allowedComponent) {
                $group->allowedComponents = [$allowedComponent];
            }
        });
    }

    protected static function addPositionTo(string $groupIri, AbstractComponent|string|null $component, int $sortValue, ?string $pageDataProperty = null): string
    {
        $existing = \is_string($component) ? static::findByIri($component) : null;
        $group = static::getEntityManager()->getRepository(ComponentGroup::class)->find(static::findByIri($groupIri)?->getId());
        if (null !== $existing) {
            $existing = static::getEntityManager()->find($existing::class, $existing->getId());
        }
        self::assertInstanceOf(ComponentGroup::class, $group);
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $position->sortValue = $sortValue;
        $position->component = $existing ?? ($component instanceof AbstractComponent ? $component : null);
        if (null !== $pageDataProperty) {
            $position->pageDataClass = PageDataWithComponent::class;
            $position->pageDataProperty = $pageDataProperty;
        }
        static::persistAndFlush(...array_filter([$component instanceof AbstractComponent ? $component : null, $position]));

        return static::iriOf($position);
    }

    protected static function addRestrictedComponentPosition(string $groupIri, int $sortValue): string
    {
        return static::addPositionTo($groupIri, new RestrictedComponent(), $sortValue);
    }

    protected static function deletePosition(string $positionIri): void
    {
        $position = static::findByIri($positionIri);
        self::assertNotNull($position);
        $manager = static::getEntityManager();
        $manager->remove($position);
        $manager->flush();
        static::rebootKernel();
    }

    /**
     * @return array{page: string, pageData: string, component: string, position: string}
     */
    protected static function createDummyComponentInPageDataAndPosition(): array
    {
        $component = new DummyComponent();
        $group = static::newComponentGroup('test');
        $page = static::newPage('test page', true);
        $pageData = new PageDataWithComponent();
        $pageData->component = $component;
        $pageData->page = $page;
        $position = new ComponentPosition();
        $position->pageDataProperty = 'component';
        $position->pageDataClass = PageDataWithComponent::class;
        $position->component = $component;
        $position->componentGroup = $group;
        $position->sortValue = 0;
        static::persistAndFlush($component, $group, $page, $pageData, $position);

        return ['page' => static::iriOf($page), 'pageData' => static::iriOf($pageData), 'component' => static::iriOf($component), 'position' => static::iriOf($position)];
    }

    /**
     * @return array{published: string, draft: string, position: string}
     */
    protected static function addPublishedComponentWithDraftTo(string $groupIri): array
    {
        $group = static::findByIri($groupIri);
        self::assertInstanceOf(ComponentGroup::class, $group);
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('-1 day'));
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $position = static::newComponentPosition($group, $published, 0);
        static::persistAndFlush($published, $draft, $position);

        return ['published' => static::iriOf($published), 'draft' => static::iriOf($draft), 'position' => static::iriOf($position)];
    }

    /**
     * @param list<string> $iris
     */
    protected static function sendOrder(Client $client, string $groupIri, array $iris): ResponseInterface
    {
        return static::requestJsonLd($client, 'PATCH', $groupIri . '/order', ['componentPositions' => $iris]);
    }

    /**
     * @param list<array{string, int}> $expected
     */
    protected static function assertOrderSortValues(array $expected): void
    {
        $sortValues = json_decode((string) self::getClient()->getResponse()?->getContent(false), false, 512, \JSON_THROW_ON_ERROR)->sortValues ?? null;
        self::assertInstanceOf(\stdClass::class, $sortValues, 'The response has no sortValues object.');
        $actual = [];
        foreach ((array) $sortValues as $iri => $sortValue) {
            $actual[] = [(string) $iri, $sortValue];
        }
        self::assertSame($expected, $actual);
    }

    protected static function assertOrderIsOf(string $groupIri): void
    {
        $json = static::lastJson();
        self::assertSame($groupIri . '/order', $json['@id'] ?? null);
        self::assertSame($groupIri, $json['componentGroup'] ?? null);
    }

    /**
     * @param list<string> $expected
     */
    protected static function assertPositionUpdatesPublishedForExactly(array $expected): void
    {
        $published = [];
        foreach (static::mercureUpdatesInLastRequest() as $update) {
            foreach ($update->getTopics() as $topic) {
                $path = parse_url($topic, \PHP_URL_PATH) ?: $topic;
                if (str_contains($path, '/component_positions/')) {
                    $published[] = $path;
                }
            }
        }
        $published = array_values(array_unique($published));
        sort($expected);
        sort($published);
        self::assertSame($expected, $published);
    }

    /**
     * @return array<mixed>
     */
    protected static function componentGroupMercureMessage(): array
    {
        $updates = static::mercureUpdatesInLastRequest();
        self::assertNotEmpty($updates, 'No Mercure update was published.');
        foreach ($updates as $update) {
            \assert($update instanceof Update);
            $data = static::mercureUpdateData($update);
            if ('/contexts/ComponentGroup' === ($data['@context'] ?? null)) {
                return $data;
            }
        }
        self::fail(\sprintf('%d updates were published but no ComponentGroup was found', \count($updates)));
    }

    /**
     * @return list<array{string, int}>
     */
    private static function parsePositions(string $positions): array
    {
        $parsed = [];
        foreach (preg_split('/\s+/', trim($positions), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            self::assertMatchesRegularExpression('/^([A-Za-z]+)(\d+)$/', $token);
            preg_match('/^([A-Za-z]+)(\d+)$/', $token, $matches);
            $parsed[] = [$matches[1], (int) $matches[2]];
        }

        return $parsed;
    }
}
