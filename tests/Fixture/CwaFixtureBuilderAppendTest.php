<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\EventListener\Console\ConsoleOutputListener;
use Silverback\ApiComponentsBundle\Exception\UnroutedParentException;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageBuilder;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureSummary;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CwaFixtureBuilderAppendTest extends TestCase
{
    /** @var list<array{string, array, object}> */
    private array $rows = [];

    /** @var list<object> */
    private array $persisted = [];

    private int $flushCount = 0;

    /** @var array<class-string, list<string>> */
    private array $owningAssociations = [];

    /** @var list<array{string, string}> */
    private array $logs = [];

    private ?RouteGeneratorInterface $routeGenerator = null;

    private function builder(?ConsoleOutputListener $consoleOutput = null): CwaFixtureBuilder
    {
        $em = $this->createStub(ObjectManager::class);
        $em->method('getRepository')->willReturnCallback(function (string $class): ObjectRepository {
            $repository = $this->createStub(ObjectRepository::class);
            $repository->method('findOneBy')->willReturnCallback(function (array $criteria) use ($class): ?object {
                foreach ($this->rows as [$rowClass, $rowCriteria, $entity]) {
                    if ($rowClass === $class && $rowCriteria === $criteria) {
                        return $entity;
                    }
                }

                return null;
            });

            return $repository;
        });
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $em->method('flush')->willReturnCallback(function (): void {
            ++$this->flushCount;
        });
        $em->method('getClassMetadata')->willReturnCallback(function (string $class): ClassMetadata {
            $metadata = $this->createStub(ClassMetadata::class);
            $metadata->method('getAssociationNames')->willReturn($this->owningAssociations[$class] ?? []);
            $metadata->method('isAssociationInverseSide')->willReturn(false);

            return $metadata;
        });

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(
            static fn (object|string $resource): string => \is_object($resource) ? '/iri/' . (isset($resource->reference) && '' !== $resource->reference ? $resource->reference : spl_object_id($resource)) : '/collection'
        );

        $logger = new class($this->logs) extends AbstractLogger {
            public function __construct(private array &$logs)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message];
            }
        };

        return (new CwaFixtureBuilder(
            $this->createStub(TimestampedDataPersister::class),
            $this->routeGenerator ?? $this->createStub(RouteGeneratorInterface::class),
            $iriConverter,
            null,
            null,
            $logger,
            $consoleOutput,
        ))->withManager($em);
    }

    private function existing(string $class, array $criteria, object $entity): object
    {
        $this->rows[] = [$class, $criteria, $entity];

        return $entity;
    }

    private function existingLayout(string $reference): Layout
    {
        $layout = new Layout();
        $layout->reference = $reference;

        return $this->existing(Layout::class, ['reference' => $reference], $layout);
    }

    private function existingPage(string $reference, ?Route $route = null): Page
    {
        $page = new Page();
        $page->reference = $reference;
        if (null !== $route) {
            $page->setRoute($route);
            $route->setPage($page);
        }

        return $this->existing(Page::class, ['reference' => $reference], $page);
    }

    private function existingGroup(string $reference): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->reference = $reference;

        return $this->existing(ComponentGroup::class, ['reference' => $reference], $group);
    }

    private function route(string $path, string $name): Route
    {
        return (new Route())->setPath($path)->setName($name);
    }

    private function labelled(string $label): DummyComponent
    {
        $component = new DummyComponent();
        $component->uiComponent = $label;

        return $component;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function persistedOf(string $class): array
    {
        return array_values(array_filter($this->persisted, static fn (object $entity) => $entity instanceof $class));
    }

    public function test_a_page_using_an_existing_layout_is_attached_to_it_and_the_layout_is_not_persisted(): void
    {
        $layout = $this->existingLayout('main');
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('about', 'Primary', layout: 'main', route: '/about');
        $cwa->flush();

        self::assertSame([], $this->persistedOf(Layout::class));
        $pages = $this->persistedOf(Page::class);
        self::assertCount(1, $pages);
        self::assertSame($layout, $pages[0]->layout);
        self::assertSame(1, $cwa->getSummary()->count(CwaFixtureSummary::KEPT, CwaFixtureSummary::LAYOUT));
        self::assertSame(1, $cwa->getSummary()->count(CwaFixtureSummary::CREATED, CwaFixtureSummary::PAGE));
    }

    public function test_an_existing_page_is_kept_untouched_and_gets_no_new_route(): void
    {
        $page = $this->existingPage('home');
        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects(self::never())->method('create');
        $this->routeGenerator = $routeGenerator;
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('home', 'Other', layout: 'main', configure: static fn (PageBuilder $builder) => $builder->title('Changed'));
        $cwa->flush();

        self::assertSame([], $this->persistedOf(Page::class));
        self::assertSame([], $this->persistedOf(Route::class));
        self::assertSame('Unnamed Page', $page->getTitle());
        self::assertFalse(isset($page->layout));
        self::assertContains(['notice', 'kept page `home`'], $this->logs);
    }

    public function test_an_existing_group_is_skipped_with_every_component_in_it_including_new_ones(): void
    {
        $page = $this->existingPage('home');
        $group = $this->existingGroup('primary_/iri/home');
        $page->getComponentGroups()->add($group);
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('home', 'Primary', layout: 'main')->group('primary')->add($this->labelled('Hero'))->add($this->labelled('Extra'));
        $cwa->flush();

        self::assertSame([], $this->persistedOf(AbstractComponent::class));
        self::assertSame([], $this->persistedOf(ComponentPosition::class));
        self::assertSame([], $this->persistedOf(ComponentGroup::class));
        self::assertSame(1, $cwa->getSummary()->count(CwaFixtureSummary::KEPT, CwaFixtureSummary::GROUP));
    }

    public function test_a_group_missing_from_an_existing_page_is_created_and_linked_with_its_components(): void
    {
        $page = $this->existingPage('home');
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('home', 'Primary', layout: 'main')->group('sidebar')->add($aside = $this->labelled('Aside'));
        $cwa->flush();

        $groups = $this->persistedOf(ComponentGroup::class);
        self::assertCount(1, $groups);
        self::assertSame('sidebar_/iri/home', $groups[0]->reference);
        self::assertTrue($page->getComponentGroups()->contains($groups[0]));
        self::assertSame([$aside], $this->persistedOf(AbstractComponent::class));
        self::assertCount(1, $this->persistedOf(ComponentPosition::class));
    }

    public function test_a_page_whose_explicit_route_path_is_taken_is_created_without_a_route_and_its_name_refers_to_the_route_at_that_path(): void
    {
        $about = $this->route('/about', 'about');
        $this->existingPage('about', $about);
        $this->existing(Route::class, ['path' => '/about'], $about);
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $builder = $cwa->page('contact', 'Primary', layout: 'main', route: '/about', routeName: 'contact');
        $cwa->flush();

        self::assertSame([$builder->getPage()], $this->persistedOf(Page::class));
        self::assertNull($builder->getPage()->getRoute());
        self::assertSame([], $this->persistedOf(Route::class));
        self::assertSame($about, $cwa->getRoute('contact'));
        self::assertSame(1, $cwa->getSummary()->count(CwaFixtureSummary::SKIPPED, CwaFixtureSummary::ROUTE, 'path in use'));
    }

    public function test_a_route_name_already_in_use_counts_as_a_taken_route(): void
    {
        $this->existing(Route::class, ['name' => 'contact'], $this->route('/contact-us', 'contact'));
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('contact', 'Primary', layout: 'main', route: '/contact', routeName: 'contact');
        $cwa->flush();

        self::assertSame([], $this->persistedOf(Route::class));
        self::assertSame('/contact-us', $cwa->getRoute('contact')->getPath());
    }

    public function test_the_named_route_of_a_kept_page_is_the_existing_route(): void
    {
        $route = $this->route('/', 'home');
        $page = $this->existingPage('home', $route);
        $this->existing(Route::class, ['path' => '/'], $route);
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $builder = $cwa->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home');
        $cwa->flush();

        self::assertSame($route, $cwa->getRoute('home'));
        self::assertSame($route, $builder->getRoute());
        self::assertSame($page, $route->getPage());
    }

    public function test_page_data_whose_explicit_route_belongs_to_page_data_of_its_class_is_kept(): void
    {
        $route = $this->route('/conference', 'conference');
        $existing = new PageData();
        $existing->setRoute($route);
        $route->setPageData($existing);
        $this->existing(Route::class, ['path' => '/conference'], $route);
        $cwa = $this->builder();

        $pageData = new PageData();
        $pageData->setTitle('Conference');
        $builder = $cwa->pageData($pageData, route: '/conference');
        $cwa->flush();

        self::assertSame([], $this->persistedOf(PageData::class));
        self::assertSame([], $this->persistedOf(Route::class));
        self::assertSame($route, $builder->getRoute());
        self::assertSame(1, $cwa->getSummary()->count(CwaFixtureSummary::KEPT, CwaFixtureSummary::PAGE_DATA));
    }

    public function test_page_data_is_matched_by_the_path_the_route_generator_would_give_it_and_no_route_is_generated(): void
    {
        $route = $this->route('/conference', 'conference');
        $existing = new PageData();
        $existing->setRoute($route);
        $route->setPageData($existing);
        $this->existing(Route::class, ['path' => '/conference'], $route);
        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->method('generatePath')->willReturn('/conference');
        $routeGenerator->expects(self::never())->method('create');
        $this->routeGenerator = $routeGenerator;
        $cwa = $this->builder();

        $pageData = new PageData();
        $pageData->setTitle('Conference');
        $cwa->pageData($pageData);
        $cwa->flush();

        self::assertSame([], $this->persistedOf(PageData::class));
    }

    public function test_page_data_whose_path_belongs_to_something_else_is_created(): void
    {
        $route = $this->route('/conference', 'conference');
        $route->setPage(new Page());
        $this->existing(Route::class, ['path' => '/conference'], $route);
        $cwa = $this->builder();

        $pageData = new PageData();
        $pageData->setTitle('Conference');
        $cwa->pageData($pageData, route: '/elsewhere');
        $cwa->flush();

        self::assertSame([$pageData], $this->persistedOf(PageData::class));
    }

    public function test_children_of_kept_page_data_are_nested_under_the_existing_entity(): void
    {
        $route = $this->route('/conference', 'conference');
        $existing = new PageData();
        $existing->setRoute($route);
        $route->setPageData($existing);
        $this->existing(Route::class, ['path' => '/conference'], $route);
        $cwa = $this->builder();

        $pageData = new PageData();
        $pageData->setTitle('Conference');
        $child = new PageData();
        $child->setTitle('Programme');
        $cwa->pageData($pageData, route: '/conference')->nested(static fn (CwaFixtureBuilder $nested) => $nested->pageData($child, route: '/conference/programme'));
        $cwa->flush();

        self::assertSame([$child], $this->persistedOf(PageData::class));
        self::assertSame($existing, $child->getParentPageData());
    }

    public function test_a_generated_child_route_under_an_existing_parent_with_no_route_still_refuses(): void
    {
        $this->existingPage('conference');
        $routeGenerator = $this->createStub(RouteGeneratorInterface::class);
        $routeGenerator->method('create')->willThrowException(new UnroutedParentException('no route'));
        $this->routeGenerator = $routeGenerator;
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('conference', 'Primary', layout: 'main', isTemplate: true)->nested(static function (CwaFixtureBuilder $nested): void {
            $pageData = new PageData();
            $pageData->setTitle('Programme');
            $nested->pageData($pageData);
        });

        $this->expectException(UnroutedParentException::class);
        $cwa->flush();
    }

    public function test_page_data_without_a_route_is_created_when_its_template_is_created(): void
    {
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('template', 'Primary', layout: 'main', isTemplate: true);
        $pageData = new PageData();
        $cwa->pageData($pageData, template: 'template')->withoutRoute();
        $cwa->flush();

        self::assertSame([$pageData], $this->persistedOf(PageData::class));
        self::assertNull($pageData->getRoute());
        self::assertSame([], $this->persistedOf(Route::class));
    }

    public function test_page_data_without_a_route_under_an_existing_template_is_skipped_as_unidentifiable(): void
    {
        $this->owningAssociations[PageDataWithComponent::class] = ['component'];
        $this->existingPage('template');
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('template', 'Primary', layout: 'main', isTemplate: true);
        $pageData = new PageDataWithComponent();
        $pageData->component = $component = $this->labelled('Intro');
        $cwa->pageData($pageData, template: 'template')->withoutRoute();
        $cwa->persist($component);
        $cwa->flush();

        self::assertSame([], $this->persistedOf(PageDataWithComponent::class));
        self::assertSame([], $this->persistedOf(AbstractComponent::class));
        self::assertSame(1, $cwa->getSummary()->count(CwaFixtureSummary::SKIPPED, CwaFixtureSummary::PAGE_DATA, 'unidentifiable'));
    }

    public function test_page_data_without_a_route_is_created_when_its_parent_is_created(): void
    {
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $child = new PageData();
        $cwa->page('parent', 'Primary', layout: 'main', route: '/parent')->nested(static fn (CwaFixtureBuilder $nested) => $nested->pageData($child)->withoutRoute());
        $cwa->flush();

        self::assertSame([$child], $this->persistedOf(PageData::class));
    }

    public function test_a_draft_linked_to_a_component_in_a_kept_group_is_not_persisted(): void
    {
        $this->owningAssociations[DummyPublishableComponent::class] = ['publishedResource'];
        $page = $this->existingPage('home');
        $page->getComponentGroups()->add($this->existingGroup('primary_/iri/home'));
        $cwa = $this->builder();

        $published = new DummyPublishableComponent();
        $cwa->layout('main', 'Primary');
        $cwa->page('home', 'Primary', layout: 'main')->group('primary')->add($published);
        $cwa->afterRoutes(static function (CwaFixtureBuilder $cwa) use ($published): void {
            $draft = new DummyPublishableComponent();
            $draft->setPublishedResource($published);
            $cwa->persist($draft);
        });
        $cwa->flush();

        self::assertSame([], $this->persistedOf(AbstractComponent::class));
        self::assertSame(1, $cwa->getSummary()->count(CwaFixtureSummary::SKIPPED, CwaFixtureSummary::COMPONENT, 'linked to skipped content'));
    }

    public function test_a_component_added_to_a_kept_group_after_routes_is_not_persisted_even_when_persisted_explicitly(): void
    {
        $page = $this->existingPage('home');
        $page->getComponentGroups()->add($this->existingGroup('primary_/iri/home'));
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $group = $cwa->page('home', 'Primary', layout: 'main')->group('primary');
        $cwa->afterRoutes(static function (CwaFixtureBuilder $cwa) use ($group): void {
            $late = new DummyComponent();
            $group->add($late);
            $cwa->persist($late);
        });
        $cwa->flush();

        self::assertSame([], $this->persistedOf(AbstractComponent::class));
    }

    public function test_a_component_in_a_kept_group_and_its_own_groups_are_not_created(): void
    {
        $page = $this->existingPage('home');
        $page->getComponentGroups()->add($this->existingGroup('primary_/iri/home'));
        $cwa = $this->builder();

        $tabs = $this->labelled('Tabs');
        $cwa->layout('main', 'Primary');
        $cwa->page('home', 'Primary', layout: 'main')->group('primary')->add($tabs);
        $cwa->component($tabs)->group('panels')->add($this->labelled('Panel'));
        $cwa->flush();

        self::assertSame([], $this->persistedOf(AbstractComponent::class));
        self::assertSame([], $this->persistedOf(ComponentGroup::class));
    }

    public function test_a_new_component_in_a_new_group_that_refers_to_a_skipped_component_is_not_placed(): void
    {
        $this->owningAssociations[DummyPublishableComponent::class] = ['publishedResource'];
        $page = $this->existingPage('home');
        $page->getComponentGroups()->add($this->existingGroup('primary_/iri/home'));
        $cwa = $this->builder();

        $published = new DummyPublishableComponent();
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $cwa->layout('main', 'Primary');
        $home = $cwa->page('home', 'Primary', layout: 'main');
        $home->group('primary')->add($published);
        $home->group('sidebar')->add($draft);
        $cwa->flush();

        self::assertSame([], $this->persistedOf(AbstractComponent::class));
        self::assertSame([], $this->persistedOf(ComponentPosition::class));
        self::assertCount(1, $this->persistedOf(ComponentGroup::class));
    }

    public function test_a_shared_group_that_already_exists_is_linked_to_a_new_owner_without_positions(): void
    {
        $group = $this->existingGroup('nav_site');
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $builder = $cwa->page('about', 'Primary', layout: 'main', route: '/about');
        $builder->group('nav', locationReference: 'site')->add($this->labelled('Link'));
        $cwa->flush();

        self::assertTrue($builder->getPage()->getComponentGroups()->contains($group));
        self::assertTrue($group->pages->contains($builder->getPage()));
        self::assertSame([], $this->persistedOf(ComponentPosition::class));
    }

    public function test_an_existing_group_newly_linked_to_an_existing_owner_is_flushed(): void
    {
        $this->existingPage('home');
        $this->existingGroup('nav_site');
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('home', 'Primary', layout: 'main')->group('nav', locationReference: 'site');
        $cwa->flush();

        self::assertGreaterThan(0, $this->flushCount);
    }

    public function test_an_existing_redirect_path_is_kept_and_registered_by_name(): void
    {
        $redirect = $this->route('/old', 'old');
        $this->existing(Route::class, ['path' => '/old'], $redirect);
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('new', 'Primary', layout: 'main', route: '/new', routeName: 'new');
        $cwa->redirect('/old', to: 'new', name: 'old-alias');
        $cwa->flush();

        self::assertSame($redirect, $cwa->getRoute('old-alias'));
        self::assertCount(1, $this->persistedOf(Route::class));
        self::assertSame(1, $cwa->getSummary()->count(CwaFixtureSummary::KEPT, CwaFixtureSummary::ROUTE));
    }

    public function test_a_redirect_whose_derived_name_belongs_to_an_existing_route_gets_a_suffix(): void
    {
        $this->existing(Route::class, ['name' => 'about'], $this->route('/about-us', 'about'));
        $this->existing(Route::class, ['name' => 'about-1'], $this->route('/about-them', 'about-1'));
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('new', 'Primary', layout: 'main', route: '/new', routeName: 'new');
        $cwa->redirect('/about', to: 'new');
        $cwa->flush();

        $redirects = array_values(array_filter($this->persistedOf(Route::class), static fn (Route $r) => null !== $r->getRedirect()));
        self::assertSame(['about-2'], array_map(static fn (Route $r) => $r->getName(), $redirects));
    }

    public function test_a_redirect_whose_given_name_belongs_to_an_existing_route_is_skipped(): void
    {
        $existing = $this->existing(Route::class, ['name' => 'old'], $this->route('/older', 'old'));
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('new', 'Primary', layout: 'main', route: '/new', routeName: 'new');
        $cwa->redirect('/old', to: 'new', name: 'old');
        $cwa->flush();

        self::assertSame([], array_values(array_filter($this->persistedOf(Route::class), static fn (Route $r) => null !== $r->getRedirect())));
        self::assertSame($existing, $cwa->getRoute('old'));
        self::assertSame(1, $cwa->getSummary()->count(CwaFixtureSummary::SKIPPED, CwaFixtureSummary::ROUTE, 'name in use'));
    }

    public function test_a_route_the_scaffold_does_not_declare_is_found_by_name(): void
    {
        $home = $this->existing(Route::class, ['name' => 'home'], $this->route('/', 'home'));
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->redirect('/old', to: 'home');
        $cwa->flush();

        self::assertSame($home, $cwa->getRoute('home'));
        $redirects = array_values(array_filter($this->persistedOf(Route::class), static fn (Route $r) => null !== $r->getRedirect()));
        self::assertCount(1, $redirects);
        self::assertSame($home, $redirects[0]->getRedirect());
    }

    public function test_on_routes_created_is_not_called_for_kept_page_data(): void
    {
        $route = $this->route('/conference', 'conference');
        $existing = new PageData();
        $existing->setRoute($route);
        $route->setPageData($existing);
        $this->existing(Route::class, ['path' => '/conference'], $route);
        $cwa = $this->builder();

        $called = false;
        $pageData = new PageData();
        $pageData->setTitle('Conference');
        $cwa->pageData($pageData, route: '/conference')->onRoutesCreated(static function () use (&$called): void {
            $called = true;
        });
        $cwa->flush();

        self::assertFalse($called);
    }

    public function test_an_empty_database_gives_a_summary_of_only_created_entities(): void
    {
        $cwa = $this->builder();

        $cwa->layout('main', 'Primary');
        $cwa->page('home', 'Primary', layout: 'main', route: '/')->group('primary')->add($this->labelled('Hero'));
        $cwa->flush();

        self::assertSame('CWA scaffold: created 1 page, 1 layout, 1 route, 1 group, 1 component', (string) $cwa->getSummary());
    }

    public function test_the_summary_is_logged_and_written_to_the_running_command_output(): void
    {
        $listener = new ConsoleOutputListener();
        $output = new BufferedOutput();
        $listener->onConsoleCommand(new ConsoleCommandEvent(new Command('doctrine:fixtures:load'), new ArrayInput([]), $output));
        $cwa = $this->builder($listener);

        $cwa->layout('main', 'Primary');
        $cwa->flush();
        $cwa->reportSummary();

        self::assertSame("  > CWA scaffold: created 1 layout\n", $output->fetch());
        self::assertContains(['info', 'CWA scaffold: created 1 layout'], $this->logs);
    }

    public function test_the_summary_starts_again_for_each_manager(): void
    {
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary');
        $cwa->flush();

        $cwa->withManager($this->createStub(ObjectManager::class));

        self::assertSame('CWA scaffold: nothing to load', (string) $cwa->getSummary());
    }
}
