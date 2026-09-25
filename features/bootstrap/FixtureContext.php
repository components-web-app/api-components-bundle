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
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Command\GenerateFixturesCommand;
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
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
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

    /** @var array<string, array<int, string>> */
    private array $recordedTables = [];

    /** @var array<string, int> */
    private array $recordedCounts = [];

    private ?string $lastSummary = null;

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
        $this->recordedTables = [];
        $this->recordedCounts = [];
        $this->lastSummary = null;
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
    public function theSiteIsGeneratedAsFixtures(string $fileName = 'GeneratedScaffold.php'): void
    {
        $this->builder()->flush();
        $this->manager->flush();
        $this->manager->clear();

        $this->outputDirectory = sys_get_temp_dir() . '/cwa-generated-fixtures-' . bin2hex(random_bytes(6));
        mkdir($this->outputDirectory);
        $this->generatedFile = $this->outputDirectory . '/' . $fileName;

        $tester = new CommandTester($this->generateFixturesCommand);
        $status = $tester->execute(['--output' => $this->generatedFile], ['interactive' => false]);
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
            $cwa->page($page, 'Primary', layout: $layout, route: $path, routeName: $page, configure: function (PageBuilder $builder) use ($group, $labels): void {
                $this->addLabelledComponents($builder->group($group), $labels);
            });
        };
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
        $cwa = $this->newBuilder();
        $this->pageDataBuilders = [];
        foreach ($this->scaffold as $definition) {
            $definition($cwa);
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
