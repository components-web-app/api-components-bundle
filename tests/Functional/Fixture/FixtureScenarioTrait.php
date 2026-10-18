<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Fixture;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use PHPUnit\Framework\Attributes\After;
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
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetLoader;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetManifest;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStore;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStoreFactory;
use Silverback\ApiComponentsBundle\Fixture\Builder\GroupBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageDataBuilder;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Fixture\Generator\ScaffoldGenerator;
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
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\UploadsTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;
use Symfony\Component\HttpFoundation\File\File;

trait FixtureScenarioTrait
{
    use ConsoleTrait;
    use SiteTrait;
    use UploadsTrait;

    private ?CwaFixtureBuilder $siteBuilder = null;
    private ?string $outputDirectory = null;
    private ?string $generatedFile = null;
    private string $commandOutput = '';

    /** @var list<\Closure(CwaFixtureBuilder): void> */
    private array $scaffold = [];

    /** @var array<string, PageDataBuilder> */
    private array $pageDataBuilders = [];

    /** @var array<string, PageBuilder> */
    private array $pageBuilders = [];

    /** @var array<string, list<\Closure(CwaFixtureBuilder): void>> */
    private array $nestedUnderPage = [];

    /** @var array<string, list<string>> */
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

    private ?FixtureAssetStoreFactory $configuredAssetStores = null;
    private ?string $assetStoreDirectory = null;

    /** @var array<string, string|bool> */
    private array $generationOptions = [];

    private ?string $previousManifest = null;

    /** @var array<string, string> */
    private array $previousStoredAssets = [];

    #[After]
    protected function removeGeneratedFixtures(): void
    {
        $filesystem = new LocalFilesystem();
        if (null !== $this->outputDirectory) {
            $filesystem->remove($this->outputDirectory);
        }
        if (null !== $this->assetStoreDirectory) {
            $filesystem->remove($this->assetStoreDirectory);
        }
    }

    protected function siteHasGroupsThatAllowAndHoldADummyComponent(): void
    {
        $cwa = $this->siteBuilder();
        $cwa->layout('main', 'Primary')->group('top', allow: [DummyComponent::class])->add(new DummyComponent());
        $cwa->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home', configure: static function (PageBuilder $page): void {
            $page->group('primary', allow: [DummyComponent::class])->add(new DummyComponent());
        });
    }

    protected function siteHasGroupsWithAndWithoutAMaximum(int $max): void
    {
        $cwa = $this->siteBuilder();
        $cwa->layout('main', 'Primary')->group('top', max: $max)->add(new DummyComponent());
        $cwa->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home', configure: static function (PageBuilder $page): void {
            $page->group('primary')->add(new DummyComponent());
        });
    }

    protected function siteHasAPublishedComponentWithADraft(string $reference, string $draftReference): void
    {
        $cwa = $this->siteBuilder();
        $published = self::newPublishedComponent($reference, '2020-01-01 00:00:00');
        $this->homePageGroup()->add($published);
        $cwa->persist(self::newDraftOf($published, $draftReference));
    }

    protected function siteHasAPublishedComponentWithAPlacedDraft(string $reference, string $draftReference): void
    {
        $published = self::newPublishedComponent($reference, '2020-01-01 00:00:00');
        $this->homePageGroup()->add($published)->add(self::newDraftOf($published, $draftReference));
    }

    protected function siteHasAnUnplacedPublishedComponentWithAPlacedDraft(string $reference, string $draftReference): void
    {
        $published = self::newPublishedComponent($reference, '2020-01-01 00:00:00');
        $this->homePageGroup()->add(self::newDraftOf($published, $draftReference));
        $this->siteBuilder()->persist($published);
    }

    protected function siteHasAPublishedComponentWithADraftOwningAGroup(string $reference, string $draftReference, string $group): void
    {
        $cwa = $this->siteBuilder();
        $published = self::newPublishedComponent($reference, '2020-01-01 00:00:00');
        $this->homePageGroup()->add($published);
        self::addLabelledComponents($cwa->component(self::newDraftOf($published, $draftReference))->group($group), 'Panel');
    }

    protected function siteHasAComponentPublishedAt(string $reference, string $date): void
    {
        $this->homePageGroup()->add(self::newPublishedComponent($reference, $date));
    }

    protected function siteHasAPageAndACollectionHoldingItsIri(string $reference, string $path, string $key): void
    {
        $collection = new Collection();
        $collection->setResourceIri('/_/pages');
        $this->homePageGroup()->add($collection);
        $cwa = $this->siteBuilder();
        $cwa->page($reference, 'Primary', layout: 'main', route: $path);
        $iriConverter = self::iriConverter();
        $cwa->afterRoutes(static function (CwaFixtureBuilder $cwa) use ($collection, $reference, $key, $iriConverter): void {
            $page = $cwa->page($reference, 'Primary', layout: 'main')->getPage();
            $collection->setDefaultQueryParameters(['pagination' => false, $key => $iriConverter->getIriFromResource($page)]);
        });
    }

    protected function siteHasACollectionHoldingTheIriOfRoutelessPageData(string $key): void
    {
        $collection = new Collection();
        $collection->setResourceIri('/_/pages');
        $this->homePageGroup()->add($collection);
        $pageData = new PageData();
        $pageData->setTitle('Draft');
        $cwa = $this->templatePage();
        $cwa->pageData($pageData, template: 'template')->withoutRoute();
        $iriConverter = self::iriConverter();
        $cwa->afterRoutes(static function () use ($collection, $pageData, $key, $iriConverter): void {
            $collection->setDefaultQueryParameters([$key => $iriConverter->getIriFromResource($pageData)]);
        });
    }

    protected function siteHasAPageReachableWithoutARoute(string $reference): void
    {
        $cwa = $this->siteBuilder();
        $cwa->layout('main', 'Primary');
        $cwa->page($reference, 'Primary', layout: 'main')->title(ucfirst($reference))->withoutRoute()->reachableWithoutRoute();
    }

    protected function siteHasAnUploadableComponentWithTheFile(string $filename): void
    {
        $component = new DummyUploadableAndPublishable();
        $component->setPublishedAt(new \DateTime('2020-01-01 00:00:00'));
        $component->file = new File(self::assetPath($filename));
        $this->homePageGroup()->add($component);
    }

    protected function siteHasUploadableComponentsWithTheFile(int $count, string $filename): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->siteHasAnUploadableComponentWithTheFile($filename);
        }
    }

    protected function siteHasAnUploadableComponentWhoseFileIsStoredAs(string $storedPath, string $filename): void
    {
        self::filesystem('local')->write($storedPath, (string) file_get_contents(self::assetPath($filename)));
        $component = new DummyUploadableAndPublishable();
        $component->setPublishedAt(new \DateTime('2020-01-01 00:00:00'));
        $component->setFilename($storedPath);
        $this->homePageGroup()->add($component);
    }

    protected function siteHasAComponentWhoseOwnGroupHoldsAComponent(string $owner, string $group, string $child): void
    {
        $tabs = self::newLabelledComponent($owner);
        $this->siteBuilder()->component($tabs)->group($group)->add(self::newLabelledComponent($child));
        $this->homePageGroup()->add($tabs);
    }

    protected function siteHasAComponentWithAnOwnGroupOnAChildPage(string $owner, string $group, string $child, string $page, string $path, string $parent): void
    {
        $tabs = self::newLabelledComponent($owner);
        $cwa = $this->siteBuilder();
        $cwa->layout('main', 'Primary');
        $parentPage = $cwa->page($parent, 'Primary', layout: 'main', route: '/' . $parent, routeName: $parent)->getPage();
        $childBuilder = $cwa->page($page, 'Primary', layout: 'main', route: $path, routeName: $page);
        $childBuilder->group('main')->add($tabs);
        $cwa->component($tabs)->group($group)->add(self::newLabelledComponent($child));
        $cwa->afterRoutes(static function () use ($childBuilder, $parentPage): void {
            $childBuilder->getPage()->setParentPage($parentPage);
        });
    }

    protected function siteHasAComponentWithAnOwnGroupUnderRoutelessPageDataAndAPage(string $owner, string $group, string $child, string $parent): void
    {
        $tabs = self::newLabelledComponent($owner);
        $cwa = $this->templatePage();
        $cwa->component($tabs)->group($group)->add(self::newLabelledComponent($child));
        $pageData = new PageData();
        $pageData->setTitle('Loose');
        $cwa->pageData($pageData, template: 'template')->withoutRoute()->nested(static function (CwaFixtureBuilder $cwa) use ($tabs): void {
            $cwa->page('first', 'Primary', layout: 'main', route: '/first', routeName: 'first')->group('main')->add($tabs);
        });
        $cwa->page($parent, 'Primary', layout: 'main', route: '/' . $parent, routeName: $parent)->nested(static function (CwaFixtureBuilder $cwa) use ($tabs, $parent): void {
            $cwa->page('second', 'Primary', layout: 'main', route: '/' . $parent . '/second', routeName: 'second')->group('main')->add($tabs);
        });
    }

    protected function siteHasASharedGroup(string $group, string $layout, string $page, string $locationReference): void
    {
        $cwa = $this->siteBuilder();
        $cwa->layout($layout, 'Primary')->group($group, locationReference: $locationReference)->add(new DummyComponent());
        $cwa->page($page, 'Primary', layout: $layout, route: '/', routeName: $page, configure: static function (PageBuilder $builder) use ($group, $locationReference): void {
            $builder->group($group, locationReference: $locationReference);
        });
    }

    protected function siteHasANavigationLink(string $path, string $date, string $theme, string $note): void
    {
        $cwa = $this->siteBuilder();
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

    protected function siteHasAPageWithAMetaDescriptionGoingLiveAt(string $reference, string $description, string $path, string $liveAt): void
    {
        $cwa = $this->siteBuilder();
        $cwa->layout('main', 'Primary');
        $builder = $cwa->page($reference, 'Primary', layout: 'main', route: $path, routeName: $reference);
        $builder->title(ucfirst($reference))->metaDescription($description);
        $cwa->flush();
        $builder->getPage()->getRoute()->setLiveAt(new \DateTimeImmutable($liveAt));
    }

    protected function siteHasAPageWithNoGoLiveDate(string $reference, string $path): void
    {
        $cwa = $this->siteBuilder();
        $cwa->layout('main', 'Primary');
        $builder = $cwa->page($reference, 'Primary', layout: 'main', route: $path, routeName: $reference);
        $cwa->flush();
        $builder->getPage()->getRoute()->setLiveAt(null);
    }

    protected function siteHasAPageWithTheUiComponent(string $reference, string $uiComponent, string $path): void
    {
        $layout = $this->flushedMainLayout();
        $page = $this->persistNewPage($reference, $layout);
        $page->uiComponent = $uiComponent;
        $page->setTitle(ucfirst($reference));
        $route = $this->persistNewRoute($path, $reference, $page);
        $page->setRoute($route);
        self::getEntityManager()->flush();
    }

    protected function siteHasARoutelessPageWithARoutedChild(string $parent, string $child, string $path): void
    {
        $layout = $this->flushedMainLayout();
        $parentPage = $this->persistNewPage($parent, $layout);
        $parentPage->setTitle(ucfirst($parent));
        $childPage = $this->persistNewPage($child, $layout);
        $childPage->setParentPage($parentPage);
        $childPage->setRoute($this->persistNewRoute($path, $child, $childPage));
        self::getEntityManager()->flush();
    }

    protected function siteHasAGrandchildPage(string $grandchild, string $path, string $child, string $childPath, string $parent, string $parentPath): void
    {
        $layout = $this->flushedMainLayout();
        $previous = null;
        foreach ([$parent => $parentPath, $child => $childPath, $grandchild => $path] as $reference => $routePath) {
            $page = $this->persistNewPage($reference, $layout);
            $page->setTitle(ucfirst($reference));
            $page->setParentPage($previous);
            $this->persistNewRoute($routePath, $reference, $page);
            $previous = $page;
        }
        self::getEntityManager()->flush();
    }

    protected function siteHasARedirect(string $from, string $to): void
    {
        $this->siteBuilder()->flush();
        $target = self::getEntityManager()->getRepository(Route::class)->findOneBy(['path' => $to]);
        $redirect = new Route();
        $redirect->setPath($from);
        $redirect->setName(trim(str_replace('/', '-', $from), '-'));
        $redirect->setRedirect($target);
        self::getContainer()->get(TimestampedDataPersister::class)->persistTimestampedFields($redirect, true);
        self::getEntityManager()->persist($redirect);
    }

    protected function sitesRouteRedirectsTo(string $path, string $to): void
    {
        $this->siteBuilder()->flush();
        $manager = self::getEntityManager();
        $manager->flush();
        $this->findRoute($path)->setRedirect($this->findRoute($to));
        $manager->flush();
    }

    protected function siteHasAPageDataWithComponent(string $title, string $uiComponent): void
    {
        $cwa = $this->templatePage();
        $pageData = new PageDataWithComponent();
        $pageData->setTitle($title);
        $pageData->component = self::newLabelledComponent($uiComponent);
        $cwa->pageData($pageData, template: 'template', route: '/' . strtolower($title));
    }

    protected function siteHasAPageDataTitledAtTheRoute(string $title, string $path): void
    {
        $pageData = new PageData();
        $pageData->setTitle($title);
        $this->templatePage()->pageData($pageData, template: 'template', route: $path);
    }

    protected function siteHasAPageDataTitledWithNoRoute(string $title): void
    {
        $pageData = new PageData();
        $pageData->setTitle($title);
        $this->templatePage()->pageData($pageData, template: 'template')->withoutRoute();
    }

    protected function siteHasAPageDataWithRoute(string $title, string $path, string $relatedPath): void
    {
        $cwa = $this->templatePage();
        $cwa->flush();
        self::getEntityManager()->flush();

        $pageData = new PageDataWithRoute();
        $pageData->setTitle($title);
        $pageData->relatedRoute = $this->findRoute($relatedPath);
        $cwa->pageData($pageData, template: 'template', route: $path);
    }

    protected function siteHasAnHtmlComponentForEachLongHtmlSample(): void
    {
        $group = $this->homePageGroup();
        foreach (self::htmlSamples() as $label => $html) {
            $component = new DummyHtmlContent();
            $component->uiComponent = $label;
            $component->html = $html;
            $group->add($component);
        }
    }

    protected function siteHasANavigationLinkInALayoutAndANestedPage(string $label, string $group, string $pageGroup, string $page, string $parent): void
    {
        $cwa = $this->siteBuilder();
        $link = new DummyNavigationLink();
        $link->label = $label;
        $cwa->layout('main', 'Primary')->group($group)->add($link);
        $cwa->page($parent, 'Primary', layout: 'main', route: '/' . $parent, routeName: $parent)->nested(static function (CwaFixtureBuilder $nested) use ($page, $parent, $pageGroup, $link): void {
            $nested->page($page, 'Primary', layout: 'main', route: '/' . $parent . '/' . $page, routeName: $page)->group($pageGroup)->add($link);
        });
    }

    protected function siteHasAPageWithADerivedRouteName(string $reference, string $path): void
    {
        $cwa = $this->siteBuilder();
        $cwa->layout('main', 'Primary');
        $cwa->page($reference, 'Primary', layout: 'main', route: $path)->title(ucfirst($reference));
    }

    protected function siteHasANavigationLinkToTheRoute(string $label, string $path): void
    {
        $cwa = $this->siteBuilder();
        $cwa->flush();
        self::getEntityManager()->flush();

        $link = new DummyNavigationLink();
        $link->label = $label;
        $link->route = $this->findRoute($path);
        $cwa->layout('main', 'Primary')->group('top')->add($link);
    }

    protected function siteHasARelatedComponentInTheSameGroup(string $name, string $related, string $group, string $page): void
    {
        $cwa = $this->siteBuilder();
        $cwa->layout('main', 'Primary');
        $target = self::newLabelledComponent($related);
        $component = new DummyComponentWithRelated();
        $component->name = $name;
        $component->related = $target;
        $cwa->page($page, 'Primary', layout: 'main', route: '/' . $page, routeName: $page)->group($group)->add($component)->add($target);
    }

    protected function siteHasARelatedComponentAcrossMethods(string $name, string $layout, string $related, string $page): void
    {
        $cwa = $this->siteBuilder();
        $target = self::newLabelledComponent($related);
        $component = new DummyComponentWithRelated();
        $component->name = $name;
        $component->related = $target;
        $cwa->layout($layout, 'Primary')->group('top')->add($component);
        $cwa->page($page, 'Primary', layout: $layout, route: '/' . $page, routeName: $page)->group('primary')->add($target);
    }

    protected function siteIsLoadedFromTheScaffold(string $file): void
    {
        $path = (string) realpath(self::fixturesDirectory() . '/' . $file);
        $code = (string) file_get_contents($path);
        $namespace = 'App\\DataFixtures\\Documented' . bin2hex(random_bytes(6));
        $code = preg_replace('/^namespace App\\\\DataFixtures;$/m', 'namespace ' . $namespace . ';', $code, 1, $count);
        self::assertSame(1, $count, \sprintf('The scaffold %s does not declare the namespace App\\DataFixtures.', $file));
        $code = str_replace('__DIR__', var_export(\dirname($path), true), (string) $code);
        $loadable = $this->outputDirectory() . '/Loadable' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($loadable, $code);
        require $loadable;

        $class = $namespace . '\\' . pathinfo($path, \PATHINFO_FILENAME);
        $manager = self::getEntityManager();
        (new ORMExecutor($manager, new ORMPurger()))->execute([new $class($this->newFixtureBuilder())]);
        $manager->clear();
    }

    protected function configureLocalAssetStore(): void
    {
        $this->assetStoreDirectory = sys_get_temp_dir() . '/cwa-fixture-assets-' . bin2hex(random_bytes(6));
        $this->configuredAssetStores = new FixtureAssetStoreFactory(self::getContainer()->get(FilesystemProvider::class), true, FixtureAssetStoreFactory::LOCAL, $this->assetStoreDirectory);
    }

    protected function configureFlysystemAssetStore(string $adapter): void
    {
        $this->configuredAssetStores = new FixtureAssetStoreFactory(self::getContainer()->get(FilesystemProvider::class), true, FixtureAssetStoreFactory::FLYSYSTEM, null, $adapter, 'fixture-assets-' . bin2hex(random_bytes(6)) . '/');
    }

    /**
     * @param array<string, string|bool> $options
     */
    protected function generateSite(string $fileName = 'GeneratedScaffold.php', array $options = []): void
    {
        $manager = self::getEntityManager();
        $this->siteBuilder()->flush();
        $manager->flush();
        $manager->clear();

        $this->generatedFile = $this->outputDirectory() . '/' . $fileName;
        $this->generationOptions = $options;
        $command = null === $this->configuredAssetStores
            ? self::getContainer()->get(GenerateFixturesCommand::class)
            : new GenerateFixturesCommand(self::getContainer()->get(ScaffoldGenerator::class), $this->configuredAssetStores);
        $tester = new CommandTester($command);
        $status = $tester->execute(['--output' => $this->generatedFile] + $options, ['interactive' => false]);
        $this->commandOutput = $tester->getDisplay();
        $manager->clear();

        self::assertSame(Command::SUCCESS, $status, \sprintf('generate-fixtures returned %d. Output: %s', $status, $this->commandOutput));
    }

    /**
     * @param array<string, string|bool> $options
     */
    protected function generateSiteAndReload(string $fileName = 'GeneratedScaffold.php', array $options = []): void
    {
        $this->generateSite($fileName, $options);
        $this->assertGeneratedFixturesAreValidPhp();
        $this->purgeAndLoadGeneratedFixtures();
    }

    protected function generateSiteAgain(): void
    {
        $this->previousGeneration = (string) file_get_contents((string) $this->generatedFile);
        $this->previousAssets = self::listFiles($this->outputDirectory() . '/assets');
        (new LocalFilesystem())->remove($this->outputDirectory() . '/assets');
        $this->generateSite(basename((string) $this->generatedFile));
    }

    protected function generateSiteAgainIntoTheSameAssetStore(): void
    {
        $this->previousGeneration = (string) file_get_contents((string) $this->generatedFile);
        $this->previousAssets = self::listFiles($this->outputDirectory() . '/assets');
        $this->previousManifest = (string) file_get_contents($this->generatedManifestPath());
        $this->previousStoredAssets = $this->storedAssets();
        $this->generateSite(basename((string) $this->generatedFile), $this->generationOptions);
    }

    protected function purgeAndLoadGeneratedFixtures(?string $fileName = null): void
    {
        if (null !== $fileName) {
            $this->generatedFile = $this->outputDirectory() . '/' . $fileName;
        }
        $scaffold = $this->generatedScaffold($this->newFixtureBuilder());
        $manager = self::getEntityManager();
        (new ORMPurger($manager))->purge();
        $manager->clear();

        $this->loadScaffoldInstance($scaffold);
    }

    protected function loadGeneratedFixturesWithoutPurging(): void
    {
        $cwa = $this->newFixtureBuilder();
        $scaffold = $this->generatedScaffold($cwa);
        self::getEntityManager()->clear();

        $this->loadScaffoldInstance($scaffold);
        $this->lastSummary = (string) $cwa->getSummary();
    }

    protected function replaceUploadableComponentFile(string $filename): void
    {
        $manager = self::getEntityManager();
        $components = $manager->getRepository(DummyUploadableAndPublishable::class)->findAll();
        self::assertCount(1, $components, 'Expected 1 uploadable component.');
        $components[0]->file = new File(self::assetPath($filename));
        self::getContainer()->get(UploadableFileManager::class)->persistFiles($components[0]);
        $manager->flush();
        $manager->clear();
    }

    protected function runCommandLine(string $command, string ...$groups): void
    {
        static::rebootKernel();
        putenv('SHELL_VERBOSITY');
        unset($_ENV['SHELL_VERBOSITY'], $_SERVER['SHELL_VERBOSITY']);
        $tester = self::runApplication(['command' => $command, '--append' => true, '--group' => $groups, '--no-interaction' => true]);
        $this->commandOutput = $tester->getDisplay();
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), \sprintf('The command returned %d. Output: %s', $tester->getStatusCode(), $this->commandOutput));
    }

    protected function assertCommandOutputContains(string $text): void
    {
        self::assertStringContainsString($text, $this->commandOutput);
    }

    protected function assertCommandOutputNotContains(string $text): void
    {
        self::assertStringNotContainsString($text, $this->commandOutput);
    }

    protected function assertGeneratedFixturesAreValidPhp(): void
    {
        exec(\sprintf('%s -l %s 2>&1', escapeshellarg(\PHP_BINARY), escapeshellarg((string) $this->generatedFile)), $lintOutput, $status);
        self::assertSame(0, $status, \sprintf("The generated fixtures are not valid PHP:\n%s\n\n%s", implode("\n", $lintOutput), $this->generatedCode()));
    }

    protected function assertGeneratedFixturesContain(string $text): void
    {
        self::assertStringContainsString($text, $this->generatedCode());
    }

    protected function assertGeneratedFixturesNotContain(string $text): void
    {
        self::assertStringNotContainsString($text, $this->generatedCode());
    }

    protected function assertNoGeneratedLineLongerThan(int $count): void
    {
        foreach (explode("\n", $this->generatedCode()) as $number => $line) {
            self::assertLessThanOrEqual($count, \strlen($line), \sprintf('Line %d of the generated fixtures is %d characters long.', $number + 1, \strlen($line)));
        }
    }

    protected function assertGenerationIdenticalToThePreviousOne(): void
    {
        self::assertSame($this->previousGeneration, $this->generatedCode(), 'The generated fixtures differ from the previous generation.');
        self::assertSame($this->previousAssets, self::listFiles($this->outputDirectory() . '/assets'), 'The exported assets differ from the previous generation.');
    }

    protected function assertGenerationManifestAndAssetStoreIdenticalToThePreviousOne(): void
    {
        $this->assertGenerationIdenticalToThePreviousOne();
        self::assertSame($this->previousManifest, (string) file_get_contents($this->generatedManifestPath()), 'The asset manifest differs from the previous generation.');
        self::assertSame($this->previousStoredAssets, $this->storedAssets(), 'The asset store differs from the previous generation.');
    }

    /**
     * @param list<string> $names
     */
    protected function assertExportedAssets(array $names): void
    {
        self::assertSame($names, array_keys(self::listFiles($this->outputDirectory() . '/assets')));
    }

    protected function assertGeneratedFixturesIdenticalTo(string $file): void
    {
        self::assertSame((string) file_get_contents(self::fixturesDirectory() . '/' . $file), $this->generatedCode(), \sprintf('The generated fixtures differ from %s.', $file));
    }

    protected function assertExportedAssetsIdenticalTo(string $directory): void
    {
        self::assertSame(self::listFiles(self::fixturesDirectory() . '/' . $directory), self::listFiles($this->outputDirectory() . '/assets'));
    }

    protected function assertAssetStoreHoldsExactlyTheManifestAssets(int $count): void
    {
        $listed = FixtureAssetManifest::fromFile($this->generatedManifestPath())->hashes();
        $stored = $this->storedAssets();
        self::assertCount($count, $listed, 'The manifest lists the wrong number of assets.');
        self::assertSame($listed, array_keys($stored), 'The asset store does not hold exactly the assets the manifest lists.');
        foreach ($stored as $hash => $contentHash) {
            self::assertSame($hash, $contentHash, \sprintf('The asset %s does not hold the content its name says.', $hash));
        }
    }

    protected function assertAssetManifestNamesTheAsset(string $name): void
    {
        $manifest = FixtureAssetManifest::fromFile($this->generatedManifestPath());
        self::assertSame([$name], array_map(static fn (string $hash): ?string => $manifest->get($hash)['name'] ?? null, $manifest->hashes()));
    }

    protected function assertNoAssetManifestWritten(): void
    {
        self::assertFileDoesNotExist($this->generatedManifestPath());
    }

    protected function scaffoldHasALayoutWithAGroup(string $layout, string $group, string $labels): void
    {
        $this->scaffold[] = static function (CwaFixtureBuilder $cwa) use ($layout, $group, $labels): void {
            self::addLabelledComponents($cwa->layout($layout, 'Primary')->group($group), $labels);
        };
    }

    protected function scaffoldHasALayoutWithALocatedGroup(string $layout, string $group, string $locationReference, string $labels): void
    {
        $this->scaffold[] = static function (CwaFixtureBuilder $cwa) use ($layout, $group, $locationReference, $labels): void {
            self::addLabelledComponents($cwa->layout($layout, 'Primary')->group($group, locationReference: $locationReference), $labels);
        };
    }

    protected function scaffoldHasALayoutWithANavigationLink(string $layout, string $group, string $label, string $routeName): void
    {
        $this->scaffold[] = static function (CwaFixtureBuilder $cwa) use ($layout, $group, $label, $routeName): void {
            $link = new DummyNavigationLink();
            $link->label = $label;
            $cwa->component($link)->linkRoute('route', $routeName);
            $cwa->layout($layout, 'Primary')->group($group)->add($link);
        };
    }

    protected function scaffoldHasAPage(string $page, string $layout, string $path, string $group, string $labels): void
    {
        $this->scaffold[] = function (CwaFixtureBuilder $cwa) use ($page, $layout, $path, $group, $labels): void {
            $this->pageBuilders[$page] = $cwa->page($page, 'Primary', layout: $layout, route: $path, routeName: $page, configure: static function (PageBuilder $builder) use ($group, $labels): void {
                self::addLabelledComponents($builder->group($group), $labels);
            });
        };
    }

    protected function scaffoldHasATemplatePage(string $page, string $layout): void
    {
        $this->scaffold[] = static function (CwaFixtureBuilder $cwa) use ($page, $layout): void {
            $cwa->page($page, 'Primary', layout: $layout, isTemplate: true);
        };
    }

    protected function scaffoldHasAPageDataAtTheRoute(string $title, string $path, string $template): void
    {
        $this->scaffold[] = function (CwaFixtureBuilder $cwa) use ($title, $path, $template): void {
            $pageData = new PageData();
            $pageData->setTitle($title);
            $this->pageDataBuilders[$title] = $cwa->pageData($pageData, template: $template, route: $path);
        };
    }

    protected function scaffoldHasANestedPageData(string $title, string $parent, string $template): void
    {
        $this->scaffold[] = function () use ($title, $parent, $template): void {
            $this->pageDataBuilders[$parent]->nested(static function (CwaFixtureBuilder $child) use ($title, $template): void {
                $pageData = new PageData();
                $pageData->setTitle($title);
                $child->pageData($pageData, template: $template);
            });
        };
    }

    protected function scaffoldHasARoutelessPageData(string $title, string $template): void
    {
        $this->scaffold[] = static function (CwaFixtureBuilder $cwa) use ($title, $template): void {
            $pageData = new PageData();
            $pageData->setTitle($title);
            $cwa->pageData($pageData, template: $template)->withoutRoute();
        };
    }

    protected function scaffoldHasARedirect(string $from, string $to): void
    {
        $this->scaffold[] = static function (CwaFixtureBuilder $cwa) use ($from, $to): void {
            $cwa->redirect($from, to: $to);
        };
    }

    protected function scaffoldsPageRedirectsTo(string $page, string $routeName): void
    {
        $this->scaffold[] = function () use ($page, $routeName): void {
            $this->pageBuilders[$page]->redirectTo($routeName);
        };
    }

    protected function scaffoldAddsComponentsToTheGroupOfThePage(string $labels, string $group, string $page): void
    {
        $this->scaffold[] = static function (CwaFixtureBuilder $cwa) use ($labels, $group, $page): void {
            self::addLabelledComponents($cwa->page($page, 'Primary', layout: 'main')->group($group), $labels);
        };
    }

    protected function scaffoldHasANestedPageWithAComponentOwningALocatedGroup(string $parent, string $page, string $path, string $group, string $owner, string $ownGroup, string $locationReference, string $labels): void
    {
        $this->nestedUnderPage[$parent][] = static function (CwaFixtureBuilder $nested) use ($page, $path, $group, $owner, $ownGroup, $locationReference, $labels): void {
            $component = self::newLabelledComponent($owner);
            $nested->page($page, 'Primary', layout: 'main', route: $path, routeName: $page)->group($group)->add($component);
            self::addLabelledComponents($nested->component($component)->group($ownGroup, locationReference: $locationReference), $labels);
        };
    }

    protected function scaffoldHasANestedComponentOwningALocatedGroup(string $parent, string $owner, string $ownGroup, string $locationReference, string $labels): void
    {
        $this->nestedUnderPage[$parent][] = static function (CwaFixtureBuilder $nested) use ($owner, $ownGroup, $locationReference, $labels): void {
            self::addLabelledComponents($nested->component(self::newLabelledComponent($owner))->group($ownGroup, locationReference: $locationReference), $labels);
        };
    }

    protected function scaffoldHasANestedPageWithAPublishedComponentAndADraft(string $parent, string $page, string $path, string $group, string $reference, string $draftReference): void
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

    protected function scaffoldHasANestedPageHoldingASharedComponent(string $parent, string $page, string $path, string $group, string $owner, string $ownGroup, string $labels): void
    {
        $this->nestedUnderPage[$parent][] = function (CwaFixtureBuilder $nested) use ($page, $path, $group, $owner, $ownGroup, $labels): void {
            if (!isset($this->sharedScaffoldComponents[$owner])) {
                $component = self::newLabelledComponent($owner);
                self::addLabelledComponents($nested->component($component)->group($ownGroup), $labels);
                $this->sharedScaffoldComponents[$owner] = $component;
            }
            $nested->page($page, 'Primary', layout: 'main', route: $path, routeName: $page)
                ->group($group)
                ->add($this->sharedScaffoldComponents[$owner]);
        };
    }

    protected function loadScaffold(): void
    {
        $this->sharedScaffoldComponents = [];
        $cwa = $this->newFixtureBuilder();
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
        self::getEntityManager()->clear();
    }

    protected function editorRenamesTheComponent(string $label, string $newLabel): void
    {
        $manager = self::getEntityManager();
        $component = $manager->getRepository(DummyComponent::class)->findOneBy(['uiComponent' => $label]);
        self::assertNotNull($component, \sprintf('There is no component "%s".', $label));
        $component->uiComponent = $newLabel;
        $manager->flush();
        $manager->clear();
    }

    protected function recordDatabaseContents(): void
    {
        self::getEntityManager()->clear();
        $this->recordedTables = self::readTables();
        $this->recordedCounts = self::countSiteEntities();
    }

    protected function assertDatabaseContentsUnchanged(): void
    {
        $current = self::readTables();
        foreach ($this->recordedTables as $table => $rows) {
            self::assertSame($rows, $current[$table] ?? [], \sprintf('The table %s changed.', $table));
        }
    }

    /**
     * @param array<string, int> $expected
     */
    protected function assertDatabaseGainedOnly(array $expected): void
    {
        foreach (self::countSiteEntities() as $entity => $count) {
            self::assertSame($expected[$entity] ?? 0, $count - $this->recordedCounts[$entity], \sprintf('Wrong number of new %s rows.', $entity));
        }
        $tables = self::readTables();
        foreach ($this->recordedTables as $name => $rows) {
            self::assertSame([], array_values(array_diff($rows, $tables[$name] ?? [])), \sprintf('Recorded rows of %s were changed or removed.', $name));
        }
    }

    protected function assertLastLoadSummary(string $summary): void
    {
        self::assertSame($summary, $this->lastSummary);
    }

    protected function assertLastLoadSummaryContains(string $text): void
    {
        self::assertStringContainsString($text, (string) $this->lastSummary);
    }

    protected function assertLastLoadSummaryNotContains(string $text): void
    {
        self::assertStringNotContainsString($text, (string) $this->lastSummary);
    }

    protected function assertGroupOfTheLayoutAllowsOnly(string $group, string $layout, string $shortName, int $count): void
    {
        $owner = self::getEntityManager()->getRepository(Layout::class)->findOneBy(['reference' => $layout]);
        self::assertGroupAllowsOnly(self::findOwnedGroup($owner?->getComponentGroups() ?? [], $group), $shortName, $count);
    }

    protected function assertGroupOfThePageAllowsOnly(string $group, string $page, string $shortName, int $count): void
    {
        $owner = self::getEntityManager()->getRepository(Page::class)->findOneBy(['reference' => $page]);
        self::assertGroupAllowsOnly(self::findOwnedGroup($owner?->getComponentGroups() ?? [], $group), $shortName, $count);
    }

    protected function assertGroupOfTheLayoutHoldsAtMost(string $group, string $layout, int $max): void
    {
        $owner = self::getEntityManager()->getRepository(Layout::class)->findOneBy(['reference' => $layout]);
        self::assertSame($max, self::findOwnedGroup($owner?->getComponentGroups() ?? [], $group)->maxComponents);
    }

    protected function assertGroupOfThePageHasNoMaximum(string $group, string $page): void
    {
        $owner = self::getEntityManager()->getRepository(Page::class)->findOneBy(['reference' => $page]);
        self::assertNull(self::findOwnedGroup($owner?->getComponentGroups() ?? [], $group)->maxComponents);
    }

    protected function assertGroupOfThePageHoldsTheComponents(string $group, string $page, string $labels): void
    {
        $positions = self::findOwnedGroup($this->findPage($page)->getComponentGroups(), $group)->componentPositions->toArray();
        usort($positions, static fn (ComponentPosition $a, ComponentPosition $b) => $a->sortValue <=> $b->sortValue);
        $held = array_map(static fn (ComponentPosition $position) => $position->component?->uiComponent, $positions);
        self::assertSame(array_map('trim', explode(',', $labels)), $held, \sprintf('The group "%s" of the page "%s" holds the wrong components.', $group, $page));
    }

    protected function assertComponentIsPublishedAndPlaced(string $reference): void
    {
        $component = $this->findPublishable($reference);
        self::assertTrue(null !== $component->getPublishedAt() && $component->getPublishedAt() <= new \DateTime(), \sprintf('The component "%s" is not published.', $reference));
        self::assertFalse($component->getComponentPositions()->isEmpty(), \sprintf('The component "%s" is not placed in any group.', $reference));
    }

    protected function assertComponentIsPublishedAndInNoPosition(string $reference): void
    {
        $component = $this->findPublishable($reference);
        self::assertTrue(null !== $component->getPublishedAt() && $component->getPublishedAt() <= new \DateTime(), \sprintf('The component "%s" is not published.', $reference));
        self::assertTrue($component->getComponentPositions()->isEmpty(), \sprintf('The component "%s" is placed in a group.', $reference));
    }

    protected function assertComponentIsPlaced(string $reference): void
    {
        self::assertFalse($this->findPublishable($reference)->getComponentPositions()->isEmpty(), \sprintf('The component "%s" is not placed in any group.', $reference));
    }

    protected function assertComponentIsAnUnpublishedDraftOf(string $draftReference, string $reference): void
    {
        $draft = $this->findPublishable($draftReference);
        self::assertNull($draft->getPublishedAt(), \sprintf('The draft "%s" has a publishedAt.', $draftReference));
        self::assertSame($reference, $draft->getPublishedResource()?->reference, \sprintf('The draft "%s" is not linked to the published component "%s".', $draftReference, $reference));
    }

    protected function assertComponentIsPublishedAt(string $reference, string $date): void
    {
        self::assertSame((new \DateTimeImmutable($date))->format(\DATE_ATOM), $this->findPublishable($reference)->getPublishedAt()?->format(\DATE_ATOM));
    }

    protected function assertComponentWasPublishedWhenItWasLoaded(string $reference): void
    {
        $publishedAt = $this->findPublishable($reference)->getPublishedAt();
        self::assertNotNull($publishedAt);
        self::assertNotNull($this->loadedAt);
        self::assertGreaterThanOrEqual($this->loadedAt->modify('-1 second'), $publishedAt);
        self::assertLessThanOrEqual(new \DateTimeImmutable('+1 second'), $publishedAt);
    }

    protected function assertPageIsReachableWithoutARoute(string $page): void
    {
        $found = $this->findPage($page);
        self::assertNull($found->getRoute(), \sprintf('The page "%s" has a route.', $page));
        self::assertTrue($found->isReachableWithoutRoute, \sprintf('The page "%s" is not reachable without a route.', $page));
    }

    protected function assertUploadableComponentHasAStoredFileWithTheSameContentAs(string $filename): void
    {
        $components = self::getEntityManager()->getRepository(DummyUploadableAndPublishable::class)->findAll();
        self::assertCount(1, $components, 'Expected 1 uploadable component.');
        self::assertStoredFileContent($components[0], $filename);
    }

    protected function assertEveryUploadableComponentHasAStoredFileWithTheSameContentAs(string $filename): void
    {
        $components = self::getEntityManager()->getRepository(DummyUploadableAndPublishable::class)->findAll();
        self::assertGreaterThanOrEqual(2, \count($components), 'Expected uploadable components.');
        foreach ($components as $component) {
            self::assertStoredFileContent($component, $filename);
        }
    }

    protected function assertUploadableComponentsStoredFileIsNamed(string $stem, string $extension): void
    {
        $components = self::getEntityManager()->getRepository(DummyUploadableAndPublishable::class)->findAll();
        $basename = 1 === \count($components) ? basename((string) $components[0]->getFilename()) : '';
        self::assertMatchesRegularExpression('/^' . preg_quote($stem, '/') . '-[0-9a-f]{8}\.' . preg_quote($extension, '/') . '$/', $basename);
    }

    protected function assertComponentOwnsAGroupHoldingTheComponent(string $owner, string $group, string $child): void
    {
        $component = self::getEntityManager()->getRepository(DummyComponent::class)->findOneBy(['uiComponent' => $owner]);
        self::assertNotNull($component, \sprintf('There is no component "%s".', $owner));
        $held = array_map(static fn (ComponentPosition $position) => $position->component?->uiComponent, self::findOwnedGroup($component->getComponentGroups(), $group)->componentPositions->toArray());
        self::assertSame([$child], $held, \sprintf('The group "%s" of "%s" holds the wrong components.', $group, $owner));
    }

    protected function assertComponentGroupsWithTheReference(int $count, string $reference): void
    {
        self::assertCount($count, self::getEntityManager()->getRepository(ComponentGroup::class)->findBy(['reference' => $reference]));
    }

    protected function assertComponentGroupBelongsToTheLayoutAndThePage(string $reference, string $layout, string $page): void
    {
        $group = self::getEntityManager()->getRepository(ComponentGroup::class)->findOneBy(['reference' => $reference]);
        self::assertSame([$layout], array_map(static fn (Layout $l) => $l->reference, $group?->layouts->toArray() ?? []));
        self::assertSame([$page], array_map(static fn (Page $p) => $p->reference, $group?->pages->toArray() ?? []));
    }

    protected function assertComponentGroupBelongsToTheComponents(string $reference, string $labels): void
    {
        $group = self::getEntityManager()->getRepository(ComponentGroup::class)->findOneBy(['reference' => $reference]);
        self::assertNotNull($group, \sprintf('There is no component group "%s".', $reference));
        $owners = array_map(static fn (AbstractComponent $component) => $component->uiComponent, $group->components->toArray());
        sort($owners);
        $expected = array_map('trim', explode(',', $labels));
        sort($expected);
        self::assertSame($expected, $owners);
    }

    protected function assertNavigationLinkPointsAtTheRoute(string $path): void
    {
        self::assertSame($path, $this->findNavigationLink()->route?->getPath());
    }

    protected function assertNavigationLinkHas(string $date, string $theme, string $note): void
    {
        $link = $this->findNavigationLink();
        self::assertSame([(new \DateTimeImmutable($date))->format(\DATE_ATOM), $theme, $note], [$link->eventDate?->format(\DATE_ATOM), $link->theme, $link->getInternalNote()]);
    }

    protected function assertNavigationLinkLabelledPointsAtTheRoute(string $label, string $path): void
    {
        $links = self::getEntityManager()->getRepository(DummyNavigationLink::class)->findBy(['label' => $label]);
        self::assertSame([$path], array_map(static fn (DummyNavigationLink $link) => $link->route?->getPath(), $links));
    }

    protected function assertNavigationLinksLabelledInPositions(int $count, string $label, int $positions): void
    {
        $links = self::getEntityManager()->getRepository(DummyNavigationLink::class)->findBy(['label' => $label]);
        self::assertCount($count, $links);
        self::assertSame($positions, array_sum(array_map(static fn (DummyNavigationLink $link) => \count($link->getComponentPositions()), $links)));
    }

    protected function assertPageHasTheMetaDescription(string $reference, string $description): void
    {
        self::assertSame($description, self::getEntityManager()->getRepository(Page::class)->findOneBy(['reference' => $reference])?->getMetaDescription());
    }

    protected function assertPageHasTheUiComponent(string $page, string $uiComponent): void
    {
        self::getEntityManager()->clear();
        self::assertSame($uiComponent, $this->findPage($page)->uiComponent);
    }

    protected function assertPageUsesTheLayout(string $page, string $layout): void
    {
        self::assertSame($layout, $this->findPage($page)->layout?->reference);
    }

    protected function assertPageHasTheParentPage(string $page, string $parent): void
    {
        self::assertSame($parent, $this->findPage($page)->getParentPage()?->reference);
    }

    protected function assertPageHasNoRoute(string $page): void
    {
        self::assertNull($this->findPage($page)->getRoute());
    }

    protected function assertRouteGoesLiveAt(string $path, string $liveAt): void
    {
        self::assertSame((new \DateTimeImmutable($liveAt))->format(\DATE_ATOM), $this->findRoute($path)->getLiveAt()?->format(\DATE_ATOM));
    }

    protected function assertRouteHasNoGoLiveDate(string $path): void
    {
        self::assertNull($this->findRoute($path)->getLiveAt());
    }

    protected function assertRouteBelongsToThePage(string $path, string $page): void
    {
        self::assertSame($page, $this->findRoute($path)->getPage()?->reference);
    }

    protected function assertRouteIsNamed(string $path, string $name): void
    {
        self::assertSame($name, $this->findRoute($path)->getName());
    }

    protected function assertRouteExists(string $path): void
    {
        $this->findRoute($path);
    }

    protected function assertComponentsLabelled(int $count, string $label): void
    {
        self::assertCount($count, self::getEntityManager()->getRepository(DummyComponent::class)->findBy(['uiComponent' => $label]));
    }

    protected function assertLayoutsWithTheReference(int $count, string $reference): void
    {
        self::assertCount($count, self::getEntityManager()->getRepository(Layout::class)->findBy(['reference' => $reference]));
    }

    protected function assertPageDataWithNoRoute(int $count): void
    {
        self::assertCount($count, self::getEntityManager()->getRepository(PageData::class)->findBy(['route' => null]));
    }

    protected function assertPageDataTitled(int $count, string $title): void
    {
        self::assertCount($count, self::getEntityManager()->getRepository(PageData::class)->findBy(['title' => $title]));
    }

    protected function assertPageDataWithComponentHoldsTheComponent(string $title, string $uiComponent): void
    {
        self::assertSame($uiComponent, self::getEntityManager()->getRepository(PageDataWithComponent::class)->findOneBy(['title' => $title])?->component?->uiComponent);
    }

    protected function assertPageDataWithRouteHasTheRelatedRoute(string $title, string $path): void
    {
        self::assertSame($path, self::getEntityManager()->getRepository(PageDataWithRoute::class)->findOneBy(['title' => $title])?->relatedRoute?->getPath());
    }

    protected function assertComponentIsRelatedToTheComponent(string $name, string $related): void
    {
        $components = self::getEntityManager()->getRepository(DummyComponentWithRelated::class)->findBy(['name' => $name]);
        self::assertSame([$related], array_map(static fn (DummyComponentWithRelated $component) => $component->related?->uiComponent, $components));
    }

    protected function assertCollectionHoldsTheIriOfThePage(string $reference, string $key): void
    {
        $collections = self::getEntityManager()->getRepository(Collection::class)->findAll();
        self::assertCount(1, $collections);
        self::assertSame(self::iriConverter()->getIriFromResource($this->findPage($reference)), $collections[0]->getDefaultQueryParameters()[$key] ?? null);
    }

    protected function assertEveryHtmlComponentHoldsExactlyItsSample(): void
    {
        foreach (self::htmlSamples() as $label => $html) {
            $components = self::getEntityManager()->getRepository(DummyHtmlContent::class)->findBy(['uiComponent' => $label]);
            self::assertCount(1, $components, \sprintf('Expected 1 HTML component "%s".', $label));
            self::assertSame($html, $components[0]->html, \sprintf('The HTML component "%s" changed.', $label));
        }
    }

    protected function assertEntitiesHaveACreatedAndAModifiedDate(int $count, string $class): void
    {
        $manager = self::getEntityManager();
        $manager->clear();
        $entities = $manager->getRepository($class)->findAll();
        self::assertCount($count, $entities);
        foreach ($entities as $entity) {
            self::assertInstanceOf(\DateTimeInterface::class, $entity->createdAt);
            self::assertInstanceOf(\DateTimeInterface::class, $entity->modifiedAt);
        }
    }

    private function siteBuilder(): CwaFixtureBuilder
    {
        return $this->siteBuilder ??= $this->newFixtureBuilder();
    }

    private function newFixtureBuilder(): CwaFixtureBuilder
    {
        $container = self::getContainer();

        return (new CwaFixtureBuilder(
            $container->get(TimestampedDataPersister::class),
            $container->get(RouteGeneratorInterface::class),
            self::iriConverter(),
            $container->get(UploadableFileManager::class),
            $container->get(UploadableAttributeReader::class),
            null,
            null,
            $container->get(PublishableAttributeReader::class),
            new FixtureAssetLoader($this->assetStores()),
        ))->withManager(self::getEntityManager());
    }

    private function assetStores(): FixtureAssetStoreFactory
    {
        return $this->configuredAssetStores ?? self::getContainer()->get(FixtureAssetStoreFactory::class);
    }

    private function generatedAssetStore(): FixtureAssetStore
    {
        $store = $this->generationOptions['--assets-store'] ?? null;
        $directory = $this->generationOptions['--assets-dir'] ?? null;
        $assetStore = $this->assetStores()->forGeneration($this->outputDirectory(), \is_string($store) ? $store : null, \is_string($directory) ? $directory : null);
        self::assertNotNull($assetStore, 'The site was generated with no fixture asset store.');

        return $assetStore;
    }

    private function generatedManifestPath(): string
    {
        return FixtureAssetManifest::pathFor($this->outputDirectory(), pathinfo((string) $this->generatedFile, \PATHINFO_FILENAME));
    }

    /**
     * @return array<string, string>
     */
    private function storedAssets(): array
    {
        $store = $this->generatedAssetStore();
        $assets = [];
        foreach ($store->hashes() as $hash) {
            $assets[$hash] = hash('sha256', (string) stream_get_contents($store->readStream($hash)));
        }

        return $assets;
    }

    private function generatedScaffold(CwaFixtureBuilder $cwa): AbstractCwaScaffold
    {
        $namespace = 'App\\DataFixtures\\RoundTrip' . bin2hex(random_bytes(6));
        $code = preg_replace('/^namespace App\\\\DataFixtures;$/m', 'namespace ' . $namespace . ';', $this->generatedCode(), 1, $count);
        self::assertSame(1, $count, "The generated fixtures do not declare the namespace App\\DataFixtures:\n" . $this->generatedCode());
        $loadable = $this->outputDirectory() . '/Loadable' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($loadable, $code);
        require $loadable;

        $class = $namespace . '\\' . pathinfo((string) $this->generatedFile, \PATHINFO_FILENAME);
        self::assertTrue(class_exists($class, false), \sprintf("The generated fixtures do not declare the class %s:\n%s", $class, $code));
        $scaffold = new $class($cwa);
        self::assertInstanceOf(AbstractCwaScaffold::class, $scaffold);

        return $scaffold;
    }

    private function loadScaffoldInstance(AbstractCwaScaffold $scaffold): void
    {
        $this->loadedAt = new \DateTimeImmutable();
        $manager = self::getEntityManager();
        $scaffold->load($manager);
        $manager->clear();
    }

    private function generatedCode(): string
    {
        return (string) file_get_contents((string) $this->generatedFile);
    }

    private function outputDirectory(): string
    {
        $this->outputDirectory ??= sys_get_temp_dir() . '/cwa-generated-fixtures-' . bin2hex(random_bytes(6));
        if (!is_dir($this->outputDirectory)) {
            mkdir($this->outputDirectory);
        }

        return $this->outputDirectory;
    }

    private function homePageGroup(): GroupBuilder
    {
        $cwa = $this->siteBuilder();
        $cwa->layout('main', 'Primary');

        return $cwa->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home')->group('primary');
    }

    private function templatePage(): CwaFixtureBuilder
    {
        $cwa = $this->siteBuilder();
        $cwa->layout('main', 'Primary');
        $cwa->page('template', 'Primary', layout: 'main', isTemplate: true);

        return $cwa;
    }

    private function flushedMainLayout(): ?Layout
    {
        $cwa = $this->siteBuilder();
        $cwa->layout('main', 'Primary');
        $cwa->flush();

        return self::getEntityManager()->getRepository(Layout::class)->findOneBy(['reference' => 'main']);
    }

    private function persistNewPage(string $reference, ?Layout $layout): Page
    {
        $page = new Page();
        $page->reference = $reference;
        $page->uiComponent = 'CwaPagePrimary';
        $page->isTemplate = false;
        $page->layout = $layout;
        self::getContainer()->get(TimestampedDataPersister::class)->persistTimestampedFields($page, true);
        self::getEntityManager()->persist($page);

        return $page;
    }

    private function persistNewRoute(string $path, string $name, Page $page): Route
    {
        $route = new Route();
        $route->setPath($path);
        $route->setName($name);
        $route->setPage($page);
        self::getContainer()->get(TimestampedDataPersister::class)->persistTimestampedFields($route, true);
        self::getEntityManager()->persist($route);

        return $route;
    }

    private function findPage(string $reference): Page
    {
        $page = self::getEntityManager()->getRepository(Page::class)->findOneBy(['reference' => $reference]);
        self::assertNotNull($page, \sprintf('There is no page "%s".', $reference));

        return $page;
    }

    private function findRoute(string $path): Route
    {
        $route = self::getEntityManager()->getRepository(Route::class)->findOneBy(['path' => $path]);
        self::assertNotNull($route, \sprintf('There is no route "%s".', $path));

        return $route;
    }

    private function findPublishable(string $reference): DummyPublishableComponent
    {
        $component = self::getEntityManager()->getRepository(DummyPublishableComponent::class)->findOneBy(['reference' => $reference]);
        self::assertNotNull($component, \sprintf('There is no component "%s".', $reference));

        return $component;
    }

    private function findNavigationLink(): DummyNavigationLink
    {
        $links = self::getEntityManager()->getRepository(DummyNavigationLink::class)->findAll();
        self::assertCount(1, $links, 'Expected 1 navigation link.');

        return $links[0];
    }

    private static function iriConverter(): IriConverterInterface
    {
        return self::getContainer()->get(IriConverterInterface::class);
    }

    private static function fixturesDirectory(): string
    {
        return __DIR__;
    }

    private static function newLabelledComponent(string $label): DummyComponent
    {
        $component = new DummyComponent();
        $component->uiComponent = $label;

        return $component;
    }

    private static function newPublishedComponent(string $reference, string $publishedAt): DummyPublishableComponent
    {
        $component = new DummyPublishableComponent();
        $component->reference = $reference;
        $component->setPublishedAt(new \DateTime($publishedAt));

        return $component;
    }

    private static function newDraftOf(DummyPublishableComponent $published, string $reference): DummyPublishableComponent
    {
        $draft = new DummyPublishableComponent();
        $draft->reference = $reference;
        $draft->setPublishedResource($published);

        return $draft;
    }

    private static function addLabelledComponents(GroupBuilder $group, string $labels): void
    {
        foreach (array_map('trim', explode(',', $labels)) as $label) {
            $group->add(self::newLabelledComponent($label));
        }
    }

    private static function assertStoredFileContent(DummyUploadableAndPublishable $component, string $filename): void
    {
        self::assertNotNull($component->getFilename(), 'The uploadable component has no stored file.');
        foreach (self::getContainer()->get(UploadableAttributeReader::class)->getConfiguredProperties($component, true) as $fieldConfiguration) {
            self::assertSame(
                (string) file_get_contents(self::assetPath($filename)),
                self::filesystem($fieldConfiguration->adapter)->read($component->getFilename()),
                \sprintf('The stored file "%s" does not have the content of "%s".', $component->getFilename(), $filename),
            );
        }
    }

    /**
     * @param iterable<ComponentGroup> $groups
     */
    private static function findOwnedGroup(iterable $groups, string $name): ComponentGroup
    {
        foreach ($groups as $group) {
            if (str_starts_with((string) $group->reference, $name . '_')) {
                return $group;
            }
        }

        self::fail(\sprintf('There is no group "%s".', $name));
    }

    private static function assertGroupAllowsOnly(ComponentGroup $group, string $shortName, int $count): void
    {
        $class = 'Silverback\\ApiComponentsBundle\\Tests\\Functional\\TestBundle\\Entity\\' . $shortName;
        $expected = [self::iriConverter()->getIriFromResource($class, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($class))];
        self::assertSame($expected, $group->allowedComponents ?? []);
        self::assertCount($count, $group->componentPositions);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function readTables(): array
    {
        $connection = self::getEntityManager()->getConnection();
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
    private static function countSiteEntities(): array
    {
        $counts = [];
        foreach (['Layout' => Layout::class, 'Page' => Page::class, 'PageData' => AbstractPageData::class, 'Route' => Route::class, 'ComponentGroup' => ComponentGroup::class, 'ComponentPosition' => ComponentPosition::class, 'Component' => AbstractComponent::class] as $name => $class) {
            $counts[$name] = self::getEntityManager()->getRepository($class)->count([]);
        }

        return $counts;
    }

    /**
     * @return array<string, string>
     */
    private static function listFiles(string $directory): array
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
}
