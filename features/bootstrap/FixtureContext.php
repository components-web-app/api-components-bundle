<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Features\Bootstrap;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Command\GenerateFixturesCommand;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
use Silverback\ApiComponentsBundle\Fixture\Builder\GroupBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageDataBuilder;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponentWithRelated;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyHtmlContent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithRoute;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\KernelInterface;

final class FixtureContext implements Context
{
    private EntityManagerInterface $manager;
    private ?CwaFixtureBuilder $cwa = null;
    private ?string $outputDirectory = null;
    private ?string $generatedFile = null;
    private string $commandOutput = '';

    /** @var array<int, \Closure(CwaFixtureBuilder): void> */
    private array $scaffold = [];

    /** @var array<string, PageDataBuilder> */
    private array $pageDataBuilders = [];

    /** @var array<string, PageBuilder> */
    private array $pageBuilders = [];

    /** @var array<string, list<\Closure(CwaFixtureBuilder): void>> */
    private array $nestedUnderPage = [];

    /** @var array<string, array<int, string>> */
    private array $recordedTables = [];

    /** @var array<string, int> */
    private array $recordedCounts = [];

    private ?string $lastSummary = null;

    private ?\DateTimeImmutable $loadedAt = null;

    private ?string $previousGeneration = null;

    /** @var array<string, string> */
    private array $previousAssets = [];

    /** @var array<string, AbstractComponent> */
    private array $sharedScaffoldComponents = [];

    public function __construct(
        ManagerRegistry $doctrine,
        private readonly TimestampedDataPersister $timestampedDataPersister,
        private readonly RouteGeneratorInterface $routeGenerator,
        private readonly IriConverterInterface $iriConverter,
        private readonly UploadableFileManager $uploadableFileManager,
        private readonly UploadableAttributeReader $uploadableAttributeReader,
        private readonly GenerateFixturesCommand $generateFixturesCommand,
        private readonly FilesystemProvider $filesystemProvider,
        private readonly KernelInterface $kernel,
        private readonly PublishableAttributeReader $publishableAttributeReader,
    ) {
        $this->manager = $doctrine->getManager();
    }

    /**
     * @AfterScenario
     */
    public function removeGeneratedFiles(): void
    {
        $this->cwa = null;
        if (null !== $this->outputDirectory && is_dir($this->outputDirectory)) {
            $this->removeDirectory($this->outputDirectory);
        }
        $this->outputDirectory = null;
        $this->generatedFile = null;
        $this->scaffold = [];
        $this->pageDataBuilders = [];
        $this->pageBuilders = [];
        $this->nestedUnderPage = [];
        $this->recordedTables = [];
        $this->recordedCounts = [];
        $this->lastSummary = null;
        $this->loadedAt = null;
        $this->sharedScaffoldComponents = [];
        $this->previousGeneration = null;
        $this->previousAssets = [];
    }

    /**
     * @Given the site has a layout group and a page group that each allow and hold a DummyComponent
     */
    public function theSiteHasGroupsThatAllowAndHoldADummyComponent(): void
    {
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary')->group('top', allow: [DummyComponent::class])->add(new DummyComponent());
        $cwa->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home', configure: static function (PageBuilder $page): void {
            $page->group('primary', allow: [DummyComponent::class])->add(new DummyComponent());
        });
    }

    /**
     * @Given the site has a published component :reference with a draft :draftReference
     */
    public function theSiteHasAPublishedComponentWithADraft(string $reference, string $draftReference): void
    {
        $cwa = $this->builder();
        $published = new DummyPublishableComponent();
        $published->reference = $reference;
        $published->setPublishedAt(new \DateTime('2020-01-01 00:00:00'));
        $this->homePageGroup()->add($published);

        $draft = new DummyPublishableComponent();
        $draft->reference = $draftReference;
        $draft->setPublishedResource($published);
        $cwa->persist($draft);
    }

    /**
     * @Given the site has a published component :reference with a draft :draftReference placed after it in the same group
     */
    public function theSiteHasAPublishedComponentWithAPlacedDraft(string $reference, string $draftReference): void
    {
        $published = new DummyPublishableComponent();
        $published->reference = $reference;
        $published->setPublishedAt(new \DateTime('2020-01-01 00:00:00'));
        $draft = new DummyPublishableComponent();
        $draft->reference = $draftReference;
        $draft->setPublishedResource($published);
        $this->homePageGroup()->add($published)->add($draft);
    }

    /**
     * @Given the site has a published component :reference in no position with a draft :draftReference placed in a group
     */
    public function theSiteHasAnUnplacedPublishedComponentWithAPlacedDraft(string $reference, string $draftReference): void
    {
        $published = new DummyPublishableComponent();
        $published->reference = $reference;
        $published->setPublishedAt(new \DateTime('2020-01-01 00:00:00'));
        $draft = new DummyPublishableComponent();
        $draft->reference = $draftReference;
        $draft->setPublishedResource($published);
        $this->homePageGroup()->add($draft);
        $this->builder()->persist($published);
    }

    /**
     * @Given the site has a page :reference at the route :path and a collection whose default query parameters hold the page's IRI under :key
     */
    public function theSiteHasAPageAndACollectionHoldingItsIri(string $reference, string $path, string $key): void
    {
        $collection = new Collection();
        $collection->setResourceIri('/_/pages');
        $this->homePageGroup()->add($collection);
        $cwa = $this->builder();
        $cwa->page($reference, 'Primary', layout: 'main', route: $path);
        $cwa->afterRoutes(function (CwaFixtureBuilder $cwa) use ($collection, $reference, $key): void {
            $page = $cwa->page($reference, 'Primary', layout: 'main')->getPage();
            $collection->setDefaultQueryParameters(['pagination' => false, $key => $this->iriConverter->getIriFromResource($page)]);
        });
    }

    /**
     * @Given the site has a collection whose default query parameters hold the IRI of page data with no route under :key
     */
    public function theSiteHasACollectionHoldingTheIriOfRoutelessPageData(string $key): void
    {
        $collection = new Collection();
        $collection->setResourceIri('/_/pages');
        $this->homePageGroup()->add($collection);
        $pageData = new PageData();
        $pageData->setTitle('Draft');
        $cwa = $this->templatePage();
        $cwa->pageData($pageData, template: 'template')->withoutRoute();
        $cwa->afterRoutes(function () use ($collection, $pageData, $key): void {
            $collection->setDefaultQueryParameters([$key => $this->iriConverter->getIriFromResource($pageData)]);
        });
    }

    /**
     * @Then the collection's default query parameters should hold the IRI of the page :reference under :key
     */
    public function theCollectionShouldHoldTheIriOfThePage(string $reference, string $key): void
    {
        $collections = $this->manager->getRepository(Collection::class)->findAll();
        if (1 !== \count($collections)) {
            throw new \RuntimeException(\sprintf('Expected 1 collection, found %d.', \count($collections)));
        }
        $expected = $this->iriConverter->getIriFromResource($this->findPage($reference));
        $actual = $collections[0]->getDefaultQueryParameters()[$key] ?? null;
        if ($expected !== $actual) {
            throw new \RuntimeException(\sprintf('The collection holds %s under "%s", expected the IRI of the page "%s", %s.', json_encode($actual), $key, $reference, $expected));
        }
    }

    /**
     * @Given the site has a page :reference with no route that is reachable without one
     */
    public function theSiteHasAPageReachableWithoutARoute(string $reference): void
    {
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary');
        $cwa->page($reference, 'Primary', layout: 'main')->title(ucfirst($reference))->withoutRoute()->reachableWithoutRoute();
    }

    /**
     * @Given the site has an uploadable component with the file :filename
     */
    public function theSiteHasAnUploadableComponentWithTheFile(string $filename): void
    {
        $component = new DummyUploadableAndPublishable();
        $component->setPublishedAt(new \DateTime('2020-01-01 00:00:00'));
        $component->file = new File($this->assetPath($filename));
        $this->homePageGroup()->add($component);
    }

    /**
     * @Given the site has an uploadable component whose file is stored as :storedPath with the content of :filename
     */
    public function theSiteHasAnUploadableComponentWhoseFileIsStoredAs(string $storedPath, string $filename): void
    {
        $this->filesystemProvider->getFilesystem('local')->write($storedPath, (string) file_get_contents($this->assetPath($filename)));
        $component = new DummyUploadableAndPublishable();
        $component->setPublishedAt(new \DateTime('2020-01-01 00:00:00'));
        $component->setFilename($storedPath);
        $this->homePageGroup()->add($component);
    }

    /**
     * @Given the site has a component :owner whose own group :group holds a component :child
     */
    public function theSiteHasAComponentWhoseOwnGroupHoldsAComponent(string $owner, string $group, string $child): void
    {
        $tabs = new DummyComponent();
        $tabs->uiComponent = $owner;
        $panel = new DummyComponent();
        $panel->uiComponent = $child;
        $this->builder()->component($tabs)->group($group)->add($panel);
        $this->homePageGroup()->add($tabs);
    }

    /**
     * @Given the site has a component :owner whose own group :group holds a component :child on the page :page at the route :path under the page :parent
     */
    public function theSiteHasAComponentWithAnOwnGroupOnAChildPage(string $owner, string $group, string $child, string $page, string $path, string $parent): void
    {
        $tabs = new DummyComponent();
        $tabs->uiComponent = $owner;
        $panel = new DummyComponent();
        $panel->uiComponent = $child;
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary');
        $parentPage = $cwa->page($parent, 'Primary', layout: 'main', route: '/' . $parent, routeName: $parent)->getPage();
        $childBuilder = $cwa->page($page, 'Primary', layout: 'main', route: $path, routeName: $page);
        $childBuilder->group('main')->add($tabs);
        $cwa->component($tabs)->group($group)->add($panel);
        $cwa->afterRoutes(static function () use ($childBuilder, $parentPage): void {
            $childBuilder->getPage()->setParentPage($parentPage);
        });
    }

    /**
     * @Given the site has a component :owner whose own group :group holds a component :child on a page nested under page data with no route and on a page nested under the page :parent
     */
    public function theSiteHasAComponentWithAnOwnGroupUnderRoutelessPageDataAndAPage(string $owner, string $group, string $child, string $parent): void
    {
        $tabs = new DummyComponent();
        $tabs->uiComponent = $owner;
        $panel = new DummyComponent();
        $panel->uiComponent = $child;
        $cwa = $this->templatePage();
        $cwa->component($tabs)->group($group)->add($panel);
        $pageData = new PageData();
        $pageData->setTitle('Loose');
        $cwa->pageData($pageData, template: 'template')->withoutRoute()->nested(static function (CwaFixtureBuilder $cwa) use ($tabs): void {
            $cwa->page('first', 'Primary', layout: 'main', route: '/first', routeName: 'first')->group('main')->add($tabs);
        });
        $cwa->page($parent, 'Primary', layout: 'main', route: '/' . $parent, routeName: $parent)->nested(static function (CwaFixtureBuilder $cwa) use ($tabs, $parent): void {
            $cwa->page('second', 'Primary', layout: 'main', route: '/' . $parent . '/second', routeName: 'second')->group('main')->add($tabs);
        });
    }

    /**
     * @Given the site has a group :group shared by the layout :layout and the page :page through the location reference :locationReference
     */
    public function theSiteHasASharedGroup(string $group, string $layout, string $page, string $locationReference): void
    {
        $cwa = $this->builder();
        $cwa->layout($layout, 'Primary')->group($group, locationReference: $locationReference)->add(new DummyComponent());
        $cwa->page($page, 'Primary', layout: $layout, route: '/', routeName: $page, configure: static function (PageBuilder $builder) use ($group, $locationReference): void {
            $builder->group($group, locationReference: $locationReference);
        });
    }

    /**
     * @Given the site has a navigation link to the page route :path with the event date :date, the theme :theme and the internal note :note
     */
    public function theSiteHasANavigationLink(string $path, string $date, string $theme, string $note): void
    {
        $cwa = $this->builder();
        $cwa->page('about', 'Primary', layout: 'main', route: $path, routeName: 'about');
        $cwa->layout('main', 'Primary');
        $cwa->flush();

        $link = new DummyNavigationLink();
        $link->label = 'About';
        $link->route = $cwa->getRoute('about');
        $link->eventDate = new \DateTimeImmutable($date);
        $link->theme = $theme;
        $link->setInternalNote($note);
        $cwa->layout('main', 'Primary')->group('top')->add($link);
    }

    /**
     * @Given the site has a page :reference with the meta description :description at the route :path going live at :liveAt
     */
    public function theSiteHasAPageWithAMetaDescriptionGoingLiveAt(string $reference, string $description, string $path, string $liveAt): void
    {
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary');
        $builder = $cwa->page($reference, 'Primary', layout: 'main', route: $path, routeName: $reference);
        $builder->title(ucfirst($reference))->metaDescription($description);
        $cwa->flush();
        $builder->getPage()->getRoute()->setLiveAt(new \DateTimeImmutable($liveAt));
    }

    /**
     * @Given the site has a page :reference at the route :path with no go-live date
     */
    public function theSiteHasAPageWithNoGoLiveDate(string $reference, string $path): void
    {
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary');
        $builder = $cwa->page($reference, 'Primary', layout: 'main', route: $path, routeName: $reference);
        $cwa->flush();
        $builder->getPage()->getRoute()->setLiveAt(null);
    }

    /**
     * @Given the site has a page :reference with the uiComponent :uiComponent at the route :path
     */
    public function theSiteHasAPageWithTheUiComponent(string $reference, string $uiComponent, string $path): void
    {
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary');
        $cwa->flush();
        $layout = $this->manager->getRepository(Layout::class)->findOneBy(['reference' => 'main']);

        $page = $this->newPage($reference, $layout);
        $page->uiComponent = $uiComponent;
        $page->setTitle(ucfirst($reference));
        $route = new Route();
        $route->setPath($path);
        $route->setName($reference);
        $route->setPage($page);
        $page->setRoute($route);
        $this->timestampedDataPersister->persistTimestampedFields($route, true);
        $this->manager->persist($route);
        $this->manager->flush();
    }

    /**
     * @Given the site has a redirect from :from to :to
     */
    public function theSiteHasARedirect(string $from, string $to): void
    {
        $this->builder()->flush();
        $target = $this->manager->getRepository(Route::class)->findOneBy(['path' => $to]);
        $redirect = new Route();
        $redirect->setPath($from);
        $redirect->setName(trim(str_replace('/', '-', $from), '-'));
        $redirect->setRedirect($target);
        $this->timestampedDataPersister->persistTimestampedFields($redirect, true);
        $this->manager->persist($redirect);
    }

    /**
     * @Given the site has a PageDataWithComponent titled :title whose component is a DummyComponent :uiComponent
     */
    public function theSiteHasAPageDataWithComponent(string $title, string $uiComponent): void
    {
        $cwa = $this->templatePage();
        $component = new DummyComponent();
        $component->uiComponent = $uiComponent;
        $pageData = new PageDataWithComponent();
        $pageData->setTitle($title);
        $pageData->component = $component;
        $cwa->pageData($pageData, template: 'template', route: '/' . strtolower($title));
    }

    /**
     * @Given the site has a PageData titled :title at the route :path
     */
    public function theSiteHasAPageDataTitledAtTheRoute(string $title, string $path): void
    {
        $cwa = $this->templatePage();
        $pageData = new PageData();
        $pageData->setTitle($title);
        $cwa->pageData($pageData, template: 'template', route: $path);
    }

    /**
     * @Given the site has a PageData titled :title with no route
     */
    public function theSiteHasAPageDataTitledWithNoRoute(string $title): void
    {
        $cwa = $this->templatePage();
        $pageData = new PageData();
        $pageData->setTitle($title);
        $cwa->pageData($pageData, template: 'template')->withoutRoute();
    }

    /**
     * @Given the site has a page :parent with no route and a child page :child at the route :path
     */
    public function theSiteHasARoutelessPageWithARoutedChild(string $parent, string $child, string $path): void
    {
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary');
        $cwa->flush();
        $layout = $this->manager->getRepository(Layout::class)->findOneBy(['reference' => 'main']);

        $parentPage = $this->newPage($parent, $layout);
        $parentPage->setTitle(ucfirst($parent));
        $childPage = $this->newPage($child, $layout);
        $childPage->setParentPage($parentPage);
        $route = new Route();
        $route->setPath($path);
        $route->setName($child);
        $route->setPage($childPage);
        $childPage->setRoute($route);
        $this->timestampedDataPersister->persistTimestampedFields($route, true);
        $this->manager->persist($route);
        $this->manager->flush();
    }

    /**
     * @When the site is generated as fixtures
     * @When the site is generated as fixtures to the file :fileName
     */
    public function theSiteIsGeneratedAsFixtures(string $fileName = 'GeneratedScaffold.php', array $options = []): void
    {
        $this->builder()->flush();
        $this->manager->flush();
        $this->manager->clear();

        $this->outputDirectory ??= sys_get_temp_dir() . '/cwa-generated-fixtures-' . bin2hex(random_bytes(6));
        if (!is_dir($this->outputDirectory)) {
            mkdir($this->outputDirectory);
        }
        $this->generatedFile = $this->outputDirectory . '/' . $fileName;

        $tester = new CommandTester($this->generateFixturesCommand);
        $status = $tester->execute(['--output' => $this->generatedFile] + $options, ['interactive' => false]);
        $this->commandOutput = $tester->getDisplay();
        $this->manager->clear();

        if (Command::SUCCESS !== $status) {
            throw new \RuntimeException(\sprintf('generate-fixtures returned %d. Output: %s', $status, $this->commandOutput));
        }
    }

    /**
     * @Then the generated fixtures should be valid PHP
     */
    public function theGeneratedFixturesShouldBeValidPhp(): void
    {
        exec(\sprintf('%s -l %s 2>&1', escapeshellarg(\PHP_BINARY), escapeshellarg($this->generatedFile)), $lintOutput, $status);
        if (0 !== $status) {
            throw new \RuntimeException(\sprintf("The generated fixtures are not valid PHP:\n%s\n\n%s", implode("\n", $lintOutput), file_get_contents($this->generatedFile)));
        }
    }

    /**
     * @Then the generated fixtures should contain :text
     */
    public function theGeneratedFixturesShouldContain(string $text): void
    {
        if (!str_contains((string) file_get_contents($this->generatedFile), $text)) {
            throw new \RuntimeException(\sprintf("The generated fixtures do not contain \"%s\":\n%s", $text, file_get_contents($this->generatedFile)));
        }
    }

    /**
     * @When the database is purged and the generated fixtures are loaded
     */
    public function theDatabaseIsPurgedAndTheGeneratedFixturesAreLoaded(): void
    {
        $scaffold = $this->generatedScaffold($this->newBuilder());

        (new ORMPurger($this->manager))->purge();
        $this->manager->clear();

        $this->loadScaffold($scaffold);
    }

    /**
     * @When the generated fixtures are loaded without purging
     */
    public function theGeneratedFixturesAreLoadedWithoutPurging(): void
    {
        $cwa = $this->newBuilder();
        $scaffold = $this->generatedScaffold($cwa);
        $this->manager->clear();

        $this->loadScaffold($scaffold);
        $this->lastSummary = (string) $cwa->getSummary();
    }

    private function generatedScaffold(CwaFixtureBuilder $cwa): AbstractCwaScaffold
    {
        $namespace = 'App\\DataFixtures\\RoundTrip' . bin2hex(random_bytes(6));
        $code = file_get_contents($this->generatedFile);
        $code = preg_replace('/^namespace App\\\\DataFixtures;$/m', 'namespace ' . $namespace . ';', $code, 1, $count);
        if (1 !== $count) {
            throw new \RuntimeException("The generated fixtures do not declare the namespace App\\DataFixtures:\n" . $code);
        }
        $loadable = $this->outputDirectory . '/Loadable' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($loadable, $code);
        require $loadable;

        $class = $namespace . '\\' . pathinfo($this->generatedFile, \PATHINFO_FILENAME);
        if (!class_exists($class, false)) {
            throw new \RuntimeException(\sprintf("The generated fixtures do not declare the class %s:\n%s", $class, $code));
        }

        return new $class($cwa);
    }

    private function loadScaffold(AbstractCwaScaffold $scaffold): void
    {
        $this->loadedAt = new \DateTimeImmutable();
        try {
            $scaffold->load($this->manager);
        } catch (\Throwable $exception) {
            throw new \RuntimeException(\sprintf("Loading the generated fixtures failed: %s\n\n%s", $exception->getMessage(), file_get_contents($this->generatedFile)), 0, $exception);
        }
        $this->manager->clear();
    }

    /**
     * @When the site is generated as fixtures and reloaded
     */
    public function theSiteIsGeneratedAsFixturesAndReloaded(): void
    {
        $this->theSiteIsGeneratedAsFixtures();
        $this->theGeneratedFixturesShouldBeValidPhp();
        $this->theDatabaseIsPurgedAndTheGeneratedFixturesAreLoaded();
    }

    /**
     * @When the site is generated as fixtures to the file :fileName and reloaded
     */
    public function theSiteIsGeneratedAsFixturesToTheFileAndReloaded(string $fileName): void
    {
        $this->theSiteIsGeneratedAsFixtures($fileName);
        $this->theGeneratedFixturesShouldBeValidPhp();
        $this->theDatabaseIsPurgedAndTheGeneratedFixturesAreLoaded();
    }

    /**
     * @Then the group :group of the layout :layout should allow only :shortName and hold :count component(s)
     */
    public function theGroupOfTheLayoutShouldAllowOnly(string $group, string $layout, string $shortName, int $count): void
    {
        $owner = $this->manager->getRepository(Layout::class)->findOneBy(['reference' => $layout]);
        $this->assertGroup($this->findOwnedGroup($owner?->getComponentGroups() ?? [], $group), $shortName, $count);
    }

    /**
     * @Then the group :group of the page :page should allow only :shortName and hold :count component(s)
     */
    public function theGroupOfThePageShouldAllowOnly(string $group, string $page, string $shortName, int $count): void
    {
        $owner = $this->manager->getRepository(Page::class)->findOneBy(['reference' => $page]);
        $this->assertGroup($this->findOwnedGroup($owner?->getComponentGroups() ?? [], $group), $shortName, $count);
    }

    /**
     * @Then the component :reference should be published and placed in a group
     */
    public function theComponentShouldBePublishedAndPlaced(string $reference): void
    {
        $component = $this->findPublishable($reference);
        if (null === $component->getPublishedAt() || $component->getPublishedAt() > new \DateTime()) {
            throw new \RuntimeException(\sprintf('The component "%s" is not published.', $reference));
        }
        if ($component->getComponentPositions()->isEmpty()) {
            throw new \RuntimeException(\sprintf('The component "%s" is not placed in any group.', $reference));
        }
    }

    /**
     * @Then the component :reference should be published and in no position
     */
    public function theComponentShouldBePublishedAndInNoPosition(string $reference): void
    {
        $component = $this->findPublishable($reference);
        if (null === $component->getPublishedAt() || $component->getPublishedAt() > new \DateTime()) {
            throw new \RuntimeException(\sprintf('The component "%s" is not published.', $reference));
        }
        if (!$component->getComponentPositions()->isEmpty()) {
            throw new \RuntimeException(\sprintf('The component "%s" is placed in a group.', $reference));
        }
    }

    /**
     * @Then the component :reference should be placed in a group
     */
    public function theComponentShouldBePlaced(string $reference): void
    {
        if ($this->findPublishable($reference)->getComponentPositions()->isEmpty()) {
            throw new \RuntimeException(\sprintf('The component "%s" is not placed in any group.', $reference));
        }
    }

    /**
     * @Then the page :page should be reachable without a route
     */
    public function thePageShouldBeReachableWithoutARoute(string $page): void
    {
        $found = $this->findPage($page);
        if (null !== $found->getRoute() || !$found->isReachableWithoutRoute) {
            throw new \RuntimeException(\sprintf('The page "%s" is not a routeless page reachable without a route.', $page));
        }
    }

    /**
     * @Then the component :draftReference should be an unpublished draft of :reference
     */
    public function theComponentShouldBeAnUnpublishedDraftOf(string $draftReference, string $reference): void
    {
        $draft = $this->findPublishable($draftReference);
        if (null !== $draft->getPublishedAt()) {
            throw new \RuntimeException(\sprintf('The draft "%s" has a publishedAt.', $draftReference));
        }
        if ($draft->getPublishedResource()?->reference !== $reference) {
            throw new \RuntimeException(\sprintf('The draft "%s" is not linked to the published component "%s".', $draftReference, $reference));
        }
    }

    /**
     * @Then the uploadable component should have a stored file with the same content as :filename
     */
    public function theUploadableComponentShouldHaveAStoredFileWithTheSameContent(string $filename): void
    {
        $components = $this->manager->getRepository(DummyUploadableAndPublishable::class)->findAll();
        if (1 !== \count($components)) {
            throw new \RuntimeException(\sprintf('Expected 1 uploadable component, found %d.', \count($components)));
        }
        $component = $components[0];
        if (null === $component->getFilename()) {
            throw new \RuntimeException('The uploadable component has no stored file.');
        }
        foreach ($this->uploadableAttributeReader->getConfiguredProperties($component, true) as $fieldConfiguration) {
            $contents = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter)->read($component->getFilename());
            if ($contents !== file_get_contents($this->assetPath($filename))) {
                throw new \RuntimeException(\sprintf('The stored file "%s" does not have the content of "%s".', $component->getFilename(), $filename));
            }
        }
    }

    /**
     * @Then the component :owner should own a group :group holding the component :child
     */
    public function theComponentShouldOwnAGroupHoldingTheComponent(string $owner, string $group, string $child): void
    {
        $component = $this->manager->getRepository(DummyComponent::class)->findOneBy(['uiComponent' => $owner]);
        if (null === $component) {
            throw new \RuntimeException(\sprintf('There is no component "%s".', $owner));
        }
        $componentGroup = $this->findOwnedGroup($component->getComponentGroups(), $group);
        $held = array_map(static fn ($position) => $position->component?->uiComponent, $componentGroup->componentPositions->toArray());
        if ([$child] !== $held) {
            throw new \RuntimeException(\sprintf('The group "%s" of "%s" holds [%s], expected [%s].', $group, $owner, implode(', ', $held), $child));
        }
    }

    /**
     * @Then there should be :count component group(s) with the reference :reference
     */
    public function thereShouldBeComponentGroupsWithTheReference(int $count, string $reference): void
    {
        $found = \count($this->manager->getRepository(ComponentGroup::class)->findBy(['reference' => $reference]));
        if ($count !== $found) {
            throw new \RuntimeException(\sprintf('Expected %d component groups with the reference "%s", found %d. All references: %s', $count, $reference, $found, implode(', ', array_map(static fn (ComponentGroup $g) => $g->reference, $this->manager->getRepository(ComponentGroup::class)->findAll()))));
        }
    }

    /**
     * @Then the component group :reference should belong to the layout :layout and the page :page
     */
    public function theComponentGroupShouldBelongToTheLayoutAndThePage(string $reference, string $layout, string $page): void
    {
        $group = $this->manager->getRepository(ComponentGroup::class)->findOneBy(['reference' => $reference]);
        $layouts = array_map(static fn (Layout $l) => $l->reference, $group?->layouts->toArray() ?? []);
        $pages = array_map(static fn (Page $p) => $p->reference, $group?->pages->toArray() ?? []);
        if ([$layout] !== $layouts || [$page] !== $pages) {
            throw new \RuntimeException(\sprintf('The group "%s" belongs to layouts [%s] and pages [%s].', $reference, implode(', ', $layouts), implode(', ', $pages)));
        }
    }

    /**
     * @Then the navigation link should point at the route :path
     */
    public function theNavigationLinkShouldPointAtTheRoute(string $path): void
    {
        $actual = $this->findNavigationLink()->route?->getPath();
        if ($path !== $actual) {
            throw new \RuntimeException(\sprintf('The navigation link points at %s, expected "%s".', var_export($actual, true), $path));
        }
    }

    /**
     * @Then the navigation link should have the event date :date, the theme :theme and the internal note :note
     */
    public function theNavigationLinkShouldHave(string $date, string $theme, string $note): void
    {
        $link = $this->findNavigationLink();
        $actual = [$link->eventDate?->format(\DATE_ATOM), $link->theme, $link->getInternalNote()];
        $expected = [(new \DateTimeImmutable($date))->format(\DATE_ATOM), $theme, $note];
        if ($expected !== $actual) {
            throw new \RuntimeException(\sprintf('The navigation link has %s, expected %s.', json_encode($actual), json_encode($expected)));
        }
    }

    /**
     * @Then the page :reference should have the meta description :description
     */
    public function thePageShouldHaveTheMetaDescription(string $reference, string $description): void
    {
        $actual = $this->manager->getRepository(Page::class)->findOneBy(['reference' => $reference])?->getMetaDescription();
        if ($description !== $actual) {
            throw new \RuntimeException(\sprintf('The page "%s" has the meta description %s.', $reference, var_export($actual, true)));
        }
    }

    /**
     * @Then the route :path should go live at :liveAt
     */
    public function theRouteShouldGoLiveAt(string $path, string $liveAt): void
    {
        $actual = $this->findRoute($path)->getLiveAt()?->format(\DATE_ATOM);
        $expected = (new \DateTimeImmutable($liveAt))->format(\DATE_ATOM);
        if ($expected !== $actual) {
            throw new \RuntimeException(\sprintf('The route "%s" goes live at %s, expected %s.', $path, var_export($actual, true), $expected));
        }
    }

    /**
     * @Then the route :path should have no go-live date
     */
    public function theRouteShouldHaveNoGoLiveDate(string $path): void
    {
        $actual = $this->findRoute($path)->getLiveAt();
        if (null !== $actual) {
            throw new \RuntimeException(\sprintf('The route "%s" goes live at %s.', $path, $actual->format(\DATE_ATOM)));
        }
    }

    /**
     * @Then the PageDataWithComponent :title should hold the component :uiComponent
     */
    public function thePageDataWithComponentShouldHoldTheComponent(string $title, string $uiComponent): void
    {
        $pageData = $this->manager->getRepository(PageDataWithComponent::class)->findOneBy(['title' => $title]);
        $actual = $pageData?->component?->uiComponent;
        if ($uiComponent !== $actual) {
            throw new \RuntimeException(\sprintf('The page data "%s" holds %s, expected "%s".', $title, var_export($actual, true), $uiComponent));
        }
    }

    /**
     * @Then there should be :count PageData titled :title
     */
    public function thereShouldBePageDataTitled(int $count, string $title): void
    {
        $found = \count($this->manager->getRepository(PageData::class)->findBy(['title' => $title]));
        if ($count !== $found) {
            throw new \RuntimeException(\sprintf('Expected %d PageData titled "%s", found %d.', $count, $title, $found));
        }
    }

    /**
     * @Given the scaffold has a layout :layout with a group :group holding the components :labels
     */
    public function theScaffoldHasALayoutWithAGroup(string $layout, string $group, string $labels): void
    {
        $this->scaffold[] = function (CwaFixtureBuilder $cwa) use ($layout, $group, $labels): void {
            $this->addLabelledComponents($cwa->layout($layout, 'Primary')->group($group), $labels);
        };
    }

    /**
     * @Given the scaffold has a page :page using the layout :layout at the route :path with a group :group holding the components :labels
     */
    public function theScaffoldHasAPage(string $page, string $layout, string $path, string $group, string $labels): void
    {
        $this->scaffold[] = function (CwaFixtureBuilder $cwa) use ($page, $layout, $path, $group, $labels): void {
            $this->pageBuilders[$page] = $cwa->page($page, 'Primary', layout: $layout, route: $path, routeName: $page, configure: function (PageBuilder $builder) use ($group, $labels): void {
                $this->addLabelledComponents($builder->group($group), $labels);
            });
        };
    }

    /**
     * @Given the scaffold has a layout :layout with a group :group at the location reference :locationReference holding the components :labels
     */
    public function theScaffoldHasALayoutWithALocatedGroup(string $layout, string $group, string $locationReference, string $labels): void
    {
        $this->scaffold[] = function (CwaFixtureBuilder $cwa) use ($layout, $group, $locationReference, $labels): void {
            $this->addLabelledComponents($cwa->layout($layout, 'Primary')->group($group, locationReference: $locationReference), $labels);
        };
    }

    /**
     * @Given the scaffold has, nested under the page :parent, a page :page at the route :path with a group :group holding a component :owner whose own group :ownGroup at the location reference :locationReference holds the components :labels
     */
    public function theScaffoldHasANestedPageWithAComponentOwningALocatedGroup(string $parent, string $page, string $path, string $group, string $owner, string $ownGroup, string $locationReference, string $labels): void
    {
        $this->nestedUnderPage[$parent][] = function (CwaFixtureBuilder $nested) use ($page, $path, $group, $owner, $ownGroup, $locationReference, $labels): void {
            $component = new DummyComponent();
            $component->uiComponent = $owner;
            $nested->page($page, 'Primary', layout: 'main', route: $path, routeName: $page)->group($group)->add($component);
            $this->addLabelledComponents($nested->component($component)->group($ownGroup, locationReference: $locationReference), $labels);
        };
    }

    /**
     * @Given the scaffold has, nested under the page :parent, a component :owner whose own group :ownGroup at the location reference :locationReference holds the components :labels
     */
    public function theScaffoldHasANestedComponentOwningALocatedGroup(string $parent, string $owner, string $ownGroup, string $locationReference, string $labels): void
    {
        $this->nestedUnderPage[$parent][] = function (CwaFixtureBuilder $nested) use ($owner, $ownGroup, $locationReference, $labels): void {
            $component = new DummyComponent();
            $component->uiComponent = $owner;
            $this->addLabelledComponents($nested->component($component)->group($ownGroup, locationReference: $locationReference), $labels);
        };
    }

    /**
     * @Then the component group :reference should belong to the components :labels
     */
    public function theComponentGroupShouldBelongToTheComponents(string $reference, string $labels): void
    {
        $group = $this->manager->getRepository(ComponentGroup::class)->findOneBy(['reference' => $reference]);
        if (null === $group) {
            throw new \RuntimeException(\sprintf('There is no component group "%s".', $reference));
        }
        $owners = array_map(static fn (AbstractComponent $component) => $component->uiComponent, $group->components->toArray());
        sort($owners);
        $expected = array_map('trim', explode(',', $labels));
        sort($expected);
        if ($expected !== $owners) {
            throw new \RuntimeException(\sprintf('The group "%s" belongs to the components [%s], expected [%s].', $reference, implode(', ', $owners), implode(', ', $expected)));
        }
    }

    /**
     * @Given the scaffold has a template page :page using the layout :layout
     */
    public function theScaffoldHasATemplatePage(string $page, string $layout): void
    {
        $this->scaffold[] = static function (CwaFixtureBuilder $cwa) use ($page, $layout): void {
            $cwa->page($page, 'Primary', layout: $layout, isTemplate: true);
        };
    }

    /**
     * @Given the scaffold has a page data titled :title at the route :path using the template :template
     */
    public function theScaffoldHasAPageDataAtTheRoute(string $title, string $path, string $template): void
    {
        $this->scaffold[] = function (CwaFixtureBuilder $cwa) use ($title, $path, $template): void {
            $pageData = new PageData();
            $pageData->setTitle($title);
            $this->pageDataBuilders[$title] = $cwa->pageData($pageData, template: $template, route: $path);
        };
    }

    /**
     * @Given the scaffold has a page data titled :title with a generated route under the page data :parent using the template :template
     */
    public function theScaffoldHasANestedPageData(string $title, string $parent, string $template): void
    {
        $this->scaffold[] = function () use ($title, $parent, $template): void {
            $this->pageDataBuilders[$parent]->nested(static function (CwaFixtureBuilder $child) use ($title, $template): void {
                $pageData = new PageData();
                $pageData->setTitle($title);
                $child->pageData($pageData, template: $template);
            });
        };
    }

    /**
     * @Given the scaffold has a page data titled :title with no route using the template :template
     */
    public function theScaffoldHasARoutelessPageData(string $title, string $template): void
    {
        $this->scaffold[] = static function (CwaFixtureBuilder $cwa) use ($title, $template): void {
            $pageData = new PageData();
            $pageData->setTitle($title);
            $cwa->pageData($pageData, template: $template)->withoutRoute();
        };
    }

    /**
     * @Given the scaffold has a redirect from :from to the route :to
     */
    public function theScaffoldHasARedirect(string $from, string $to): void
    {
        $this->scaffold[] = static function (CwaFixtureBuilder $cwa) use ($from, $to): void {
            $cwa->redirect($from, to: $to);
        };
    }

    /**
     * @Then the route :path should be named :name
     */
    public function theRouteShouldBeNamed(string $path, string $name): void
    {
        $actual = $this->findRoute($path)->getName();
        if ($name !== $actual) {
            throw new \RuntimeException(\sprintf('The route "%s" is named %s, expected "%s".', $path, var_export($actual, true), $name));
        }
    }

    /**
     * @Given the scaffold adds the components :labels to the group :group of the page :page
     * @Given the scaffold adds a group :group holding the components :labels to the page :page
     */
    public function theScaffoldAddsComponentsToTheGroupOfThePage(string $labels, string $group, string $page): void
    {
        $this->scaffold[] = function (CwaFixtureBuilder $cwa) use ($labels, $group, $page): void {
            $this->addLabelledComponents($cwa->page($page, 'Primary', layout: 'main')->group($group), $labels);
        };
    }

    /**
     * @When the scaffold is loaded
     * @When the scaffold is loaded again without purging
     */
    public function theScaffoldIsLoaded(): void
    {
        $this->sharedScaffoldComponents = [];
        $cwa = $this->newBuilder();
        $this->pageDataBuilders = [];
        $this->pageBuilders = [];
        foreach ($this->scaffold as $definition) {
            $definition($cwa);
        }
        foreach ($this->nestedUnderPage as $parent => $definitions) {
            $this->pageBuilders[$parent]->nested(static function (CwaFixtureBuilder $nested) use ($definitions): void {
                foreach ($definitions as $definition) {
                    $definition($nested);
                }
            });
        }
        $cwa->flush();
        $this->lastSummary = (string) $cwa->getSummary();
        $this->manager->clear();
    }

    /**
     * @When an editor renames the component :label to :newLabel
     */
    public function anEditorRenamesTheComponent(string $label, string $newLabel): void
    {
        $component = $this->manager->getRepository(DummyComponent::class)->findOneBy(['uiComponent' => $label]);
        if (null === $component) {
            throw new \RuntimeException(\sprintf('There is no component "%s".', $label));
        }
        $component->uiComponent = $newLabel;
        $this->manager->flush();
        $this->manager->clear();
    }

    /**
     * @When the database contents are recorded
     */
    public function theDatabaseContentsAreRecorded(): void
    {
        $this->manager->clear();
        $this->recordedTables = $this->readTables();
        $this->recordedCounts = $this->countEntities();
    }

    /**
     * @Then the database contents should be unchanged
     */
    public function theDatabaseContentsShouldBeUnchanged(): void
    {
        $current = $this->readTables();
        foreach ($this->recordedTables as $table => $rows) {
            if (($current[$table] ?? []) !== $rows) {
                throw new \RuntimeException(\sprintf("The table %s changed.\nBefore:\n%s\nAfter:\n%s", $table, implode("\n", $rows), implode("\n", $current[$table] ?? [])));
            }
        }
    }

    /**
     * @Then the database should have gained only:
     */
    public function theDatabaseShouldHaveGainedOnly(TableNode $table): void
    {
        $expected = array_map('intval', $table->getRowsHash());
        foreach ($this->countEntities() as $entity => $count) {
            $gained = $count - $this->recordedCounts[$entity];
            if (($expected[$entity] ?? 0) !== $gained) {
                throw new \RuntimeException(\sprintf('Expected %d new %s rows, found %d.', $expected[$entity] ?? 0, $entity, $gained));
            }
        }
        $tables = $this->readTables();
        foreach ($this->recordedTables as $name => $rows) {
            $missing = array_diff($rows, $tables[$name] ?? []);
            if ([] !== $missing) {
                throw new \RuntimeException(\sprintf("Recorded rows of %s were changed or removed:\n%s", $name, implode("\n", $missing)));
            }
        }
    }

    /**
     * @Then there should be :count component(s) labelled :label
     */
    public function thereShouldBeComponentsLabelled(int $count, string $label): void
    {
        $found = \count($this->manager->getRepository(DummyComponent::class)->findBy(['uiComponent' => $label]));
        if ($count !== $found) {
            throw new \RuntimeException(\sprintf('Expected %d components labelled "%s", found %d.', $count, $label, $found));
        }
    }

    /**
     * @Then the last load summary should be :summary
     */
    public function theLastLoadSummaryShouldBe(string $summary): void
    {
        if ($summary !== $this->lastSummary) {
            throw new \RuntimeException(\sprintf('The last load summary was "%s".', $this->lastSummary));
        }
    }

    /**
     * @Then the last load summary should contain :text
     */
    public function theLastLoadSummaryShouldContain(string $text): void
    {
        if (!str_contains((string) $this->lastSummary, $text)) {
            throw new \RuntimeException(\sprintf('The last load summary "%s" does not contain "%s".', $this->lastSummary, $text));
        }
    }

    /**
     * @Then the last load summary should not contain :text
     */
    public function theLastLoadSummaryShouldNotContain(string $text): void
    {
        if (str_contains((string) $this->lastSummary, $text)) {
            throw new \RuntimeException(\sprintf('The last load summary "%s" contains "%s".', $this->lastSummary, $text));
        }
    }

    /**
     * @Then there should be :count layout(s) with the reference :reference
     */
    public function thereShouldBeLayoutsWithTheReference(int $count, string $reference): void
    {
        $found = \count($this->manager->getRepository(Layout::class)->findBy(['reference' => $reference]));
        if ($count !== $found) {
            throw new \RuntimeException(\sprintf('Expected %d layouts with the reference "%s", found %d.', $count, $reference, $found));
        }
    }

    /**
     * @Then the page :page should have the uiComponent :uiComponent
     */
    public function thePageShouldHaveTheUiComponent(string $page, string $uiComponent): void
    {
        $this->manager->clear();
        $actual = $this->findPage($page)->uiComponent;
        if ($uiComponent !== $actual) {
            throw new \RuntimeException(\sprintf('The page "%s" has the uiComponent %s.', $page, var_export($actual, true)));
        }
    }

    /**
     * @Then the page :page should use the layout :layout
     */
    public function thePageShouldUseTheLayout(string $page, string $layout): void
    {
        $actual = $this->findPage($page)->layout?->reference;
        if ($layout !== $actual) {
            throw new \RuntimeException(\sprintf('The page "%s" uses the layout %s.', $page, var_export($actual, true)));
        }
    }

    /**
     * @Then the page :page should have the parent page :parent
     */
    public function thePageShouldHaveTheParentPage(string $page, string $parent): void
    {
        $parentReference = $this->findPage($page)->getParentPage()?->reference;
        if ($parent !== $parentReference) {
            throw new \RuntimeException(\sprintf('The page "%s" has the parent page "%s", expected "%s".', $page, $parentReference ?? 'none', $parent));
        }
    }

    /**
     * @Then the page :page should have no route
     */
    public function thePageShouldHaveNoRoute(string $page): void
    {
        $route = $this->findPage($page)->getRoute();
        if (null !== $route) {
            throw new \RuntimeException(\sprintf('The page "%s" has the route "%s".', $page, $route->getPath()));
        }
    }

    /**
     * @Then the route :path should belong to the page :page
     */
    public function theRouteShouldBelongToThePage(string $path, string $page): void
    {
        $actual = $this->findRoute($path)->getPage()?->reference;
        if ($page !== $actual) {
            throw new \RuntimeException(\sprintf('The route "%s" belongs to the page %s.', $path, var_export($actual, true)));
        }
    }

    /**
     * @Then there should be a route :path
     */
    public function thereShouldBeARoute(string $path): void
    {
        $this->findRoute($path);
    }

    /**
     * @Then the group :group of the page :page should hold the components :labels
     */
    public function theGroupOfThePageShouldHoldTheComponents(string $group, string $page, string $labels): void
    {
        $componentGroup = $this->findOwnedGroup($this->findPage($page)->getComponentGroups(), $group);
        $positions = $componentGroup->componentPositions->toArray();
        usort($positions, static fn (ComponentPosition $a, ComponentPosition $b) => $a->sortValue <=> $b->sortValue);
        $held = array_map(static fn (ComponentPosition $position) => $position->component?->uiComponent, $positions);
        $expected = array_map('trim', explode(',', $labels));
        if ($expected !== $held) {
            throw new \RuntimeException(\sprintf('The group "%s" of the page "%s" holds [%s], expected [%s].', $group, $page, implode(', ', $held), implode(', ', $expected)));
        }
    }

    /**
     * @Then there should be :count PageData with no route
     */
    public function thereShouldBePageDataWithNoRoute(int $count): void
    {
        $found = \count($this->manager->getRepository(PageData::class)->findBy(['route' => null]));
        if ($count !== $found) {
            throw new \RuntimeException(\sprintf('Expected %d PageData with no route, found %d.', $count, $found));
        }
    }

    /**
     * @When I run the command :command
     */
    public function iRunTheCommand(string $command): void
    {
        $kernel = new \AppKernel($this->kernel->getEnvironment(), $this->kernel->isDebug());
        $kernel->boot();
        try {
            $application = new Application($kernel);
            $application->setAutoExit(false);
            $application->setCatchExceptions(false);
            $output = new BufferedOutput();
            $status = $application->run(new StringInput($command . ' --no-interaction'), $output);
            $this->commandOutput = $output->fetch();
        } finally {
            $kernel->shutdown();
        }
        if (Command::SUCCESS !== $status) {
            throw new \RuntimeException(\sprintf('The command returned %d. Output: %s', $status, $this->commandOutput));
        }
    }

    /**
     * @Then the command output should contain :text
     */
    public function theCommandOutputShouldContain(string $text): void
    {
        if (!str_contains($this->commandOutput, $text)) {
            throw new \RuntimeException(\sprintf('The command output does not contain "%s": %s', $text, $this->commandOutput));
        }
    }

    /**
     * @Then the command output should not contain :text
     */
    public function theCommandOutputShouldNotContain(string $text): void
    {
        if (str_contains($this->commandOutput, $text)) {
            throw new \RuntimeException(\sprintf('The command output contains "%s": %s', $text, $this->commandOutput));
        }
    }

    /**
     * @Given the site is loaded from the scaffold :file
     */
    public function theSiteIsLoadedFromTheScaffold(string $file): void
    {
        $path = (string) realpath(__DIR__ . '/../fixtures/' . $file);
        $code = (string) file_get_contents($path);
        $namespace = 'App\\DataFixtures\\Documented' . bin2hex(random_bytes(6));
        $code = preg_replace('/^namespace App\\\\DataFixtures;$/m', 'namespace ' . $namespace . ';', $code, 1, $count);
        if (1 !== $count) {
            throw new \RuntimeException(\sprintf('The scaffold %s does not declare the namespace App\\DataFixtures.', $file));
        }
        $code = str_replace('__DIR__', var_export(\dirname($path), true), $code);
        $this->outputDirectory ??= sys_get_temp_dir() . '/cwa-generated-fixtures-' . bin2hex(random_bytes(6));
        if (!is_dir($this->outputDirectory)) {
            mkdir($this->outputDirectory);
        }
        $loadable = $this->outputDirectory . '/Loadable' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($loadable, $code);
        require $loadable;

        $class = $namespace . '\\' . pathinfo($path, \PATHINFO_FILENAME);
        $scaffold = new $class($this->newBuilder());
        (new ORMExecutor($this->manager, new ORMPurger()))->execute([$scaffold]);
        $this->manager->clear();
    }

    /**
     * @When the site is generated as fixtures with the option :option
     */
    public function theSiteIsGeneratedAsFixturesWithTheOption(string $option): void
    {
        $this->theSiteIsGeneratedAsFixtures('GeneratedScaffold.php', [$option => true]);
    }

    /**
     * @When the site is generated as fixtures with the option :option and reloaded
     */
    public function theSiteIsGeneratedAsFixturesWithTheOptionAndReloaded(string $option): void
    {
        $this->theSiteIsGeneratedAsFixturesWithTheOption($option);
        $this->theGeneratedFixturesShouldBeValidPhp();
        $this->theDatabaseIsPurgedAndTheGeneratedFixturesAreLoaded();
    }

    /**
     * @Then the generated fixtures should not contain :text
     */
    public function theGeneratedFixturesShouldNotContain(string $text): void
    {
        if (str_contains((string) file_get_contents($this->generatedFile), $text)) {
            throw new \RuntimeException(\sprintf("The generated fixtures contain \"%s\":\n%s", $text, file_get_contents($this->generatedFile)));
        }
    }

    /**
     * @When the site is generated as fixtures again
     */
    public function theSiteIsGeneratedAsFixturesAgain(): void
    {
        $this->previousGeneration = (string) file_get_contents($this->generatedFile);
        $this->previousAssets = $this->listFiles($this->outputDirectory . '/assets');
        if (is_dir($this->outputDirectory . '/assets')) {
            $this->removeDirectory($this->outputDirectory . '/assets');
        }
        $this->theSiteIsGeneratedAsFixtures(basename($this->generatedFile));
    }

    /**
     * @Then the generated fixtures and their assets should be identical to the previous generation
     */
    public function theGeneratedFixturesAndAssetsShouldBeIdenticalToThePreviousGeneration(): void
    {
        $actual = (string) file_get_contents($this->generatedFile);
        if ($this->previousGeneration !== $actual) {
            $previous = $this->outputDirectory . '/previous-generation.php';
            file_put_contents($previous, (string) $this->previousGeneration);
            exec(\sprintf('diff -u %s %s 2>&1', escapeshellarg($previous), escapeshellarg($this->generatedFile)), $diff);

            throw new \RuntimeException(\sprintf("The generated fixtures differ from the previous generation:\n%s", implode("\n", $diff)));
        }
        $assets = $this->listFiles($this->outputDirectory . '/assets');
        if ($this->previousAssets !== $assets) {
            throw new \RuntimeException(\sprintf('The exported assets are %s, the previous generation exported %s.', json_encode($assets), json_encode($this->previousAssets)));
        }
    }

    /**
     * @Then the exported assets should be :names
     */
    public function theExportedAssetsShouldBe(string $names): void
    {
        $actual = array_keys($this->listFiles($this->outputDirectory . '/assets'));
        $expected = array_map('trim', explode(',', $names));
        if ($expected !== $actual) {
            throw new \RuntimeException(\sprintf('The exported assets are %s, expected %s.', json_encode($actual), json_encode($expected)));
        }
    }

    /**
     * @Then the generated fixtures should be identical to :file
     */
    public function theGeneratedFixturesShouldBeIdenticalTo(string $file): void
    {
        $expected = __DIR__ . '/../fixtures/' . $file;
        if (file_get_contents($expected) === file_get_contents($this->generatedFile)) {
            return;
        }
        exec(\sprintf('diff -u %s %s 2>&1', escapeshellarg($expected), escapeshellarg($this->generatedFile)), $diff);

        throw new \RuntimeException(\sprintf("The generated fixtures differ from %s:\n%s", $file, implode("\n", $diff)));
    }

    /**
     * @Then the exported assets should be identical to :directory
     */
    public function theExportedAssetsShouldBeIdenticalTo(string $directory): void
    {
        $expected = $this->listFiles(__DIR__ . '/../fixtures/' . $directory);
        $actual = $this->listFiles($this->outputDirectory . '/assets');
        if ($expected !== $actual) {
            throw new \RuntimeException(\sprintf('The exported assets are %s, expected %s.', json_encode(array_keys($actual)), json_encode(array_keys($expected))));
        }
    }

    /**
     * @Given the site has an HTML component for each long HTML sample
     */
    public function theSiteHasAnHtmlComponentForEachLongHtmlSample(): void
    {
        $group = $this->homePageGroup();
        foreach (self::htmlSamples() as $label => $html) {
            $component = new DummyHtmlContent();
            $component->uiComponent = $label;
            $component->html = $html;
            $group->add($component);
        }
    }

    /**
     * @Then every HTML component should hold exactly its sample
     */
    public function everyHtmlComponentShouldHoldExactlyItsSample(): void
    {
        foreach (self::htmlSamples() as $label => $html) {
            $components = $this->manager->getRepository(DummyHtmlContent::class)->findBy(['uiComponent' => $label]);
            if (1 !== \count($components)) {
                throw new \RuntimeException(\sprintf('Expected 1 HTML component "%s", found %d.', $label, \count($components)));
            }
            if ($html !== $components[0]->html) {
                throw new \RuntimeException(\sprintf("The HTML component \"%s\" holds:\n%s\nexpected:\n%s", $label, var_export($components[0]->html, true), var_export($html, true)));
            }
        }
    }

    /**
     * @Then no line of the generated fixtures should be longer than :count characters
     */
    public function noLineOfTheGeneratedFixturesShouldBeLongerThan(int $count): void
    {
        foreach (explode("\n", (string) file_get_contents($this->generatedFile)) as $number => $line) {
            if (\strlen($line) > $count) {
                throw new \RuntimeException(\sprintf('Line %d of the generated fixtures is %d characters long.', $number + 1, \strlen($line)));
            }
        }
    }

    /**
     * @Given the site has a published component :reference published at :date
     */
    public function theSiteHasAComponentPublishedAt(string $reference, string $date): void
    {
        $component = new DummyPublishableComponent();
        $component->reference = $reference;
        $component->setPublishedAt(new \DateTime($date));
        $this->homePageGroup()->add($component);
    }

    /**
     * @Then the component :reference should be published at :date
     */
    public function theComponentShouldBePublishedAt(string $reference, string $date): void
    {
        $actual = $this->findPublishable($reference)->getPublishedAt()?->format(\DATE_ATOM);
        $expected = (new \DateTimeImmutable($date))->format(\DATE_ATOM);
        if ($expected !== $actual) {
            throw new \RuntimeException(\sprintf('The component "%s" is published at %s, expected %s.', $reference, var_export($actual, true), $expected));
        }
    }

    /**
     * @Then the component :reference should have been published when it was loaded
     */
    public function theComponentShouldHaveBeenPublishedWhenItWasLoaded(string $reference): void
    {
        $publishedAt = $this->findPublishable($reference)->getPublishedAt();
        $earliest = $this->loadedAt?->modify('-1 second');
        $latest = new \DateTimeImmutable('+1 second');
        if (null === $publishedAt || null === $earliest || $publishedAt < $earliest || $publishedAt > $latest) {
            throw new \RuntimeException(\sprintf('The component "%s" is published at %s, not when it was loaded at %s.', $reference, $publishedAt?->format(\DATE_ATOM) ?? 'null', $this->loadedAt?->format(\DATE_ATOM) ?? 'never'));
        }
    }

    /**
     * @Given the site has a navigation link :label in the layout group :group and in the group :pageGroup of the page :page nested under the page :parent
     */
    public function theSiteHasANavigationLinkInALayoutAndANestedPage(string $label, string $group, string $pageGroup, string $page, string $parent): void
    {
        $cwa = $this->builder();
        $link = new DummyNavigationLink();
        $link->label = $label;
        $cwa->layout('main', 'Primary')->group($group)->add($link);
        $cwa->page($parent, 'Primary', layout: 'main', route: '/' . $parent, routeName: $parent)->nested(static function (CwaFixtureBuilder $nested) use ($page, $parent, $pageGroup, $link): void {
            $nested->page($page, 'Primary', layout: 'main', route: '/' . $parent . '/' . $page, routeName: $page)->group($pageGroup)->add($link);
        });
    }

    /**
     * @Then there should be :count navigation link(s) labelled :label in :positions position(s)
     */
    public function thereShouldBeNavigationLinksLabelledInPositions(int $count, string $label, int $positions): void
    {
        $links = $this->manager->getRepository(DummyNavigationLink::class)->findBy(['label' => $label]);
        $found = array_sum(array_map(static fn (DummyNavigationLink $link) => \count($link->getComponentPositions()), $links));
        if ($count !== \count($links) || $positions !== $found) {
            throw new \RuntimeException(\sprintf('Expected %d navigation links labelled "%s" in %d positions, found %d in %d.', $count, $label, $positions, \count($links), $found));
        }
    }

    /**
     * @Given the site has a page :grandchild at the route :path under the page :child at the route :childPath under the page :parent at the route :parentPath
     */
    public function theSiteHasAGrandchildPage(string $grandchild, string $path, string $child, string $childPath, string $parent, string $parentPath): void
    {
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary');
        $cwa->flush();
        $layout = $this->manager->getRepository(Layout::class)->findOneBy(['reference' => 'main']);

        $previous = null;
        foreach ([$parent => $parentPath, $child => $childPath, $grandchild => $path] as $reference => $routePath) {
            $page = $this->newPage($reference, $layout);
            $page->setTitle(ucfirst($reference));
            $page->setParentPage($previous);
            $route = new Route();
            $route->setPath($routePath);
            $route->setName($reference);
            $route->setPage($page);
            $this->timestampedDataPersister->persistTimestampedFields($route, true);
            $this->manager->persist($route);
            $previous = $page;
        }
        $this->manager->flush();
    }

    /**
     * @Given the site has a page :reference at the route :path with a derived route name
     */
    public function theSiteHasAPageWithADerivedRouteName(string $reference, string $path): void
    {
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary');
        $cwa->page($reference, 'Primary', layout: 'main', route: $path)->title(ucfirst($reference));
    }

    /**
     * @Given the site has a navigation link :label to the route :path
     */
    public function theSiteHasANavigationLinkToTheRoute(string $label, string $path): void
    {
        $cwa = $this->builder();
        $cwa->flush();
        $this->manager->flush();

        $link = new DummyNavigationLink();
        $link->label = $label;
        $link->route = $this->findRoute($path);
        $cwa->layout('main', 'Primary')->group('top')->add($link);
    }

    /**
     * @Then the navigation link :label should point at the route :path
     */
    public function theNavigationLinkLabelledShouldPointAtTheRoute(string $label, string $path): void
    {
        $links = $this->manager->getRepository(DummyNavigationLink::class)->findBy(['label' => $label]);
        $actual = array_map(static fn (DummyNavigationLink $link) => $link->route?->getPath(), $links);
        if ([$path] !== $actual) {
            throw new \RuntimeException(\sprintf('The navigation links labelled "%s" point at %s, expected ["%s"].', $label, json_encode($actual), $path));
        }
    }

    /**
     * @Given the site has a component :name related to a component :related in the group :group of the page :page
     */
    public function theSiteHasARelatedComponentInTheSameGroup(string $name, string $related, string $group, string $page): void
    {
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary');
        $target = new DummyComponent();
        $target->uiComponent = $related;
        $component = new DummyComponentWithRelated();
        $component->name = $name;
        $component->related = $target;
        $cwa->page($page, 'Primary', layout: 'main', route: '/' . $page, routeName: $page)->group($group)->add($component)->add($target);
    }

    /**
     * @Given the site has a component :name in the layout :layout related to a component :related on the page :page
     */
    public function theSiteHasARelatedComponentAcrossMethods(string $name, string $layout, string $related, string $page): void
    {
        $cwa = $this->builder();
        $target = new DummyComponent();
        $target->uiComponent = $related;
        $component = new DummyComponentWithRelated();
        $component->name = $name;
        $component->related = $target;
        $cwa->layout($layout, 'Primary')->group('top')->add($component);
        $cwa->page($page, 'Primary', layout: $layout, route: '/' . $page, routeName: $page)->group('primary')->add($target);
    }

    /**
     * @Then the component :name should be related to the component :related
     */
    public function theComponentShouldBeRelatedToTheComponent(string $name, string $related): void
    {
        $components = $this->manager->getRepository(DummyComponentWithRelated::class)->findBy(['name' => $name]);
        $actual = array_map(static fn (DummyComponentWithRelated $component) => $component->related?->uiComponent, $components);
        if ([$related] !== $actual) {
            throw new \RuntimeException(\sprintf('The components named "%s" are related to %s, expected ["%s"].', $name, json_encode($actual), $related));
        }
    }

    /**
     * @Given the site has a PageDataWithRoute titled :title at the route :path whose related route is :relatedPath
     */
    public function theSiteHasAPageDataWithRoute(string $title, string $path, string $relatedPath): void
    {
        $cwa = $this->templatePage();
        $cwa->flush();
        $this->manager->flush();

        $pageData = new PageDataWithRoute();
        $pageData->setTitle($title);
        $pageData->relatedRoute = $this->findRoute($relatedPath);
        $cwa->pageData($pageData, template: 'template', route: $path);
    }

    /**
     * @Then the PageDataWithRoute :title should have the related route :path
     */
    public function thePageDataWithRouteShouldHaveTheRelatedRoute(string $title, string $path): void
    {
        $actual = $this->manager->getRepository(PageDataWithRoute::class)->findOneBy(['title' => $title])?->relatedRoute?->getPath();
        if ($path !== $actual) {
            throw new \RuntimeException(\sprintf('The page data "%s" has the related route %s, expected "%s".', $title, var_export($actual, true), $path));
        }
    }

    /**
     * @Given the site has a published component :reference with a draft :draftReference that owns a group :group
     */
    public function theSiteHasAPublishedComponentWithADraftOwningAGroup(string $reference, string $draftReference, string $group): void
    {
        $cwa = $this->builder();
        $published = new DummyPublishableComponent();
        $published->reference = $reference;
        $published->setPublishedAt(new \DateTime('2020-01-01 00:00:00'));
        $this->homePageGroup()->add($published);

        $draft = new DummyPublishableComponent();
        $draft->reference = $draftReference;
        $draft->setPublishedResource($published);
        $this->addLabelledComponents($cwa->component($draft)->group($group), 'Panel');
    }

    /**
     * @Given the scaffold has, nested under the page :parent, a page :page at the route :path with a group :group holding a published component :reference with a draft :draftReference
     */
    public function theScaffoldHasANestedPageWithAPublishedComponentAndADraft(string $parent, string $page, string $path, string $group, string $reference, string $draftReference): void
    {
        $this->nestedUnderPage[$parent][] = static function (CwaFixtureBuilder $nested) use ($page, $path, $group, $reference, $draftReference): void {
            $published = new DummyPublishableComponent();
            $published->reference = $reference;
            $nested->publish($published);

            $draft = new DummyPublishableComponent();
            $draft->reference = $draftReference;
            $nested->component($published)->draft($draft);

            $nested->page($page, 'Primary', layout: 'main', route: $path, routeName: $page)
                ->group($group)
                ->add($published);
        };
    }

    /**
     * @Given the scaffold has a layout :layout with a group :group holding a navigation link :label to the route named :routeName
     */
    public function theScaffoldHasALayoutWithANavigationLink(string $layout, string $group, string $label, string $routeName): void
    {
        $this->scaffold[] = static function (CwaFixtureBuilder $cwa) use ($layout, $group, $label, $routeName): void {
            $link = new DummyNavigationLink();
            $link->label = $label;
            $cwa->component($link)->linkRoute('route', $routeName);
            $cwa->layout($layout, 'Primary')->group($group)->add($link);
        };
    }

    /**
     * @Given the scaffold has, nested under the page :parent, a page :page at the route :path whose group :group holds the shared component :owner with its own group :ownGroup holding the components :labels
     */
    public function theScaffoldHasANestedPageHoldingASharedComponent(string $parent, string $page, string $path, string $group, string $owner, string $ownGroup, string $labels): void
    {
        $this->nestedUnderPage[$parent][] = function (CwaFixtureBuilder $nested) use ($page, $path, $group, $owner, $ownGroup, $labels): void {
            if (!isset($this->sharedScaffoldComponents[$owner])) {
                $component = new DummyComponent();
                $component->uiComponent = $owner;
                $this->addLabelledComponents($nested->component($component)->group($ownGroup), $labels);
                $this->sharedScaffoldComponents[$owner] = $component;
            }
            $nested->page($page, 'Primary', layout: 'main', route: $path, routeName: $page)
                ->group($group)
                ->add($this->sharedScaffoldComponents[$owner]);
        };
    }

    /**
     * @return array<string, string>
     */
    private static function htmlSamples(): array
    {
        return [
            'pre' => "<p>Some introduction text that is long enough to need more than one piece.</p><pre><code>first line\n    second line\n</code></pre>\n",
            'paragraph' => '<p>' . str_repeat('word ', 1000) . '</p>',
            'upper-case' => str_repeat('<P>An upper-case paragraph that keeps on going.</P>', 4),
            'quotes' => str_repeat('<p class="note">It\'s a \\path\\ with "quotes" and a \' quote.</p>', 5),
            'blocks' => str_repeat('<p>' . str_repeat('Some words in a paragraph. ', 37) . '</p>', 6),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function listFiles(string $directory): array
    {
        $files = [];
        if (!is_dir($directory)) {
            return $files;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[substr($file->getPathname(), \strlen($directory) + 1)] = (string) md5_file($file->getPathname());
        }
        ksort($files);

        return $files;
    }

    private function addLabelledComponents(GroupBuilder $group, string $labels): void
    {
        foreach (array_map('trim', explode(',', $labels)) as $label) {
            $component = new DummyComponent();
            $component->uiComponent = $label;
            $group->add($component);
        }
    }

    private function findPage(string $reference): Page
    {
        $page = $this->manager->getRepository(Page::class)->findOneBy(['reference' => $reference]);
        if (null === $page) {
            throw new \RuntimeException(\sprintf('There is no page "%s".', $reference));
        }

        return $page;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function readTables(): array
    {
        $connection = $this->manager->getConnection();
        $tables = [];
        foreach ($connection->createSchemaManager()->listTableNames() as $table) {
            $rows = array_map(static function (array $row): string {
                ksort($row);

                return (string) json_encode($row);
            }, $connection->fetchAllAssociative(\sprintf('SELECT * FROM %s', $connection->quoteSingleIdentifier($table))));
            sort($rows);
            $tables[$table] = $rows;
        }

        return $tables;
    }

    /**
     * @return array<string, int>
     */
    private function countEntities(): array
    {
        $counts = [];
        foreach (['Layout' => Layout::class, 'Page' => Page::class, 'PageData' => AbstractPageData::class, 'Route' => Route::class, 'ComponentGroup' => ComponentGroup::class, 'ComponentPosition' => ComponentPosition::class, 'Component' => AbstractComponent::class] as $name => $class) {
            $counts[$name] = $this->manager->getRepository($class)->count([]);
        }

        return $counts;
    }

    private function builder(): CwaFixtureBuilder
    {
        return $this->cwa ??= $this->newBuilder();
    }

    private function newBuilder(): CwaFixtureBuilder
    {
        return (new CwaFixtureBuilder(
            $this->timestampedDataPersister,
            $this->routeGenerator,
            $this->iriConverter,
            $this->uploadableFileManager,
            $this->uploadableAttributeReader,
            null,
            null,
            $this->publishableAttributeReader,
        ))->withManager($this->manager);
    }

    private function newPage(string $reference, ?Layout $layout): Page
    {
        $page = new Page();
        $page->reference = $reference;
        $page->uiComponent = 'CwaPagePrimary';
        $page->isTemplate = false;
        $page->layout = $layout;
        $this->timestampedDataPersister->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        return $page;
    }

    private function homePageGroup(): GroupBuilder
    {
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary');

        return $cwa->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home')->group('primary');
    }

    private function templatePage(): CwaFixtureBuilder
    {
        $cwa = $this->builder();
        $cwa->layout('main', 'Primary');
        $cwa->page('template', 'Primary', layout: 'main', isTemplate: true);

        return $cwa;
    }

    private function assetPath(string $filename): string
    {
        return __DIR__ . '/../assets/files/' . $filename;
    }

    /**
     * @param iterable<ComponentGroup> $groups
     */
    private function findOwnedGroup(iterable $groups, string $name): ComponentGroup
    {
        foreach ($groups as $group) {
            if (str_starts_with((string) $group->reference, $name . '_')) {
                return $group;
            }
        }

        throw new \RuntimeException(\sprintf('There is no group "%s".', $name));
    }

    private function assertGroup(ComponentGroup $group, string $shortName, int $count): void
    {
        $class = 'Silverback\\ApiComponentsBundle\\Tests\\Functional\\TestBundle\\Entity\\' . $shortName;
        $expected = [$this->iriConverter->getIriFromResource($class, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($class))];
        if ($expected !== ($group->allowedComponents ?? [])) {
            throw new \RuntimeException(\sprintf('The group "%s" allows %s, expected %s.', $group->reference, json_encode($group->allowedComponents), json_encode($expected)));
        }
        $held = \count($group->componentPositions);
        if ($count !== $held) {
            throw new \RuntimeException(\sprintf('The group "%s" holds %d components, expected %d.', $group->reference, $held, $count));
        }
    }

    private function findPublishable(string $reference): DummyPublishableComponent
    {
        $component = $this->manager->getRepository(DummyPublishableComponent::class)->findOneBy(['reference' => $reference]);
        if (null === $component) {
            throw new \RuntimeException(\sprintf('There is no component "%s".', $reference));
        }

        return $component;
    }

    private function findNavigationLink(): DummyNavigationLink
    {
        $links = $this->manager->getRepository(DummyNavigationLink::class)->findAll();
        if (1 !== \count($links)) {
            throw new \RuntimeException(\sprintf('Expected 1 navigation link, found %d.', \count($links)));
        }

        return $links[0];
    }

    private function findRoute(string $path): Route
    {
        $route = $this->manager->getRepository(Route::class)->findOneBy(['path' => $path]);
        if (null === $route) {
            throw new \RuntimeException(\sprintf('There is no route "%s".', $path));
        }

        return $route;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
    }
}
