<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Maker;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Maker\MakeCwaScaffold;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Maker\Fixture\PublishableNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Maker\Fixture\ReadOnlyLabelNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Maker\Fixture\SetterNavigationLink;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MakeCwaScaffoldTest extends TestCase
{
    private ?string $renderedFile = null;
    private string $display = '';

    protected function tearDown(): void
    {
        if (null !== $this->renderedFile && file_exists($this->renderedFile)) {
            unlink($this->renderedFile);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function linkClasses(): iterable
    {
        yield 'a publishable link' => [PublishableNavigationLink::class];
        yield 'a link that is not publishable' => [DummyNavigationLink::class];
        yield 'a link written through setters' => [SetterNavigationLink::class];
        yield 'a link whose label cannot be written' => [ReadOnlyLabelNavigationLink::class];
        yield 'a missing class' => ['App\\Entity\\NavigationLink'];
        yield 'a component with no label or route' => [DummyComponent::class];
        yield 'no class' => [''];
    }

    #[DataProvider('linkClasses')]
    public function test_the_skeleton_lints(string $linkClass): void
    {
        $code = $this->render(linkClass: $linkClass);

        exec(\sprintf('%s -l %s 2>&1', escapeshellarg(\PHP_BINARY), escapeshellarg((string) $this->renderedFile)), $lint, $status);
        self::assertSame(0, $status, implode("\n", $lint) . "\n" . $code);
    }

    #[DataProvider('linkClasses')]
    public function test_every_import_of_the_skeleton_is_used(string $linkClass): void
    {
        $code = $this->render(linkClass: $linkClass);

        preg_match_all('/^use [\w\\\\]+\\\\(\w+);$/m', $code, $imports);
        $body = (string) preg_replace('/^use .*$/m', '', $code);
        foreach ($imports[1] as $shortName) {
            self::assertMatchesRegularExpression('/\b' . $shortName . '\b/', $body, $shortName . ' is imported but not used.');
        }
    }

    public function test_a_link_class_that_cannot_be_a_navigation_link_leaves_the_link_out_and_says_so(): void
    {
        $code = $this->render(linkClass: 'App\\Entity\\NavigationLink');

        self::assertStringNotContainsString('NavigationLink', $code);
        self::assertStringNotContainsString('linkRoute(', $code);
        self::assertStringContainsString("\$cwa->layout('main', uiComponent: 'Primary')\n            ->group('header');\n", $code);
        self::assertStringContainsString('App\\Entity\\NavigationLink is not a component class with a label and a', $this->display);
    }

    public function test_a_link_that_is_not_publishable_is_not_published(): void
    {
        $code = $this->render(linkClass: DummyNavigationLink::class);

        self::assertStringContainsString("\$cwa->component(\$homeDummyNavigationLink)->linkRoute('route', routeName: 'home');", $code);
        self::assertStringNotContainsString('$cwa->publish(', $code);
    }

    public function test_the_page_ui_component_is_given_without_its_prefix(): void
    {
        self::assertStringContainsString("\$cwa->page('home', uiComponent: 'Primary', layout: 'main', route: '/', routeName: 'home')", $this->render());
    }

    public function test_a_hyphenated_layout_reference_gives_a_camel_cased_method(): void
    {
        $code = $this->render(layoutRef: 'side-bar');

        self::assertStringContainsString('    private function sideBarLayout(CwaFixtureBuilder $cwa): void', $code);
        self::assertStringContainsString("\$cwa->layout('side-bar', uiComponent: 'Primary')", $code);
    }

    public function test_the_skeleton_has_a_method_for_the_layout_and_the_home_page_called_from_build(): void
    {
        $code = $this->render(layoutRef: 'site', layoutComponent: 'Wide');

        self::assertStringContainsString("        \$this->siteLayout(\$cwa);\n        \$this->homePage(\$cwa);\n", $code);
        self::assertStringContainsString('    private function siteLayout(CwaFixtureBuilder $cwa): void', $code);
        self::assertStringContainsString('    private function homePage(CwaFixtureBuilder $cwa): void', $code);
        self::assertStringContainsString("\$cwa->layout('site', uiComponent: 'Wide')", $code);
        self::assertStringContainsString("layout: 'site'", $code);
    }

    public function test_the_skeleton_links_to_the_home_route_and_publishes(): void
    {
        $code = $this->render();

        self::assertStringContainsString("        \$homePublishableNavigationLink = new PublishableNavigationLink();\n        \$homePublishableNavigationLink->label = 'Home';\n        \$cwa->publish(\$homePublishableNavigationLink);\n        \$cwa->component(\$homePublishableNavigationLink)->linkRoute('route', routeName: 'home');\n", $code);
        self::assertStringNotContainsString('getRoute(', $code);
        self::assertStringNotContainsString('setPublishedAt', $code);
    }

    public function test_a_link_with_a_label_setter_is_labelled_through_it(): void
    {
        $code = $this->render(linkClass: SetterNavigationLink::class);

        self::assertStringContainsString("        \$homeSetterNavigationLink->setLabel('Home');\n", $code);
        self::assertStringNotContainsString('->label =', $code);
    }

    public function test_a_link_whose_label_cannot_be_written_is_left_out_and_says_so(): void
    {
        $code = $this->render(linkClass: ReadOnlyLabelNavigationLink::class);

        self::assertStringNotContainsString('NavigationLink', $code);
        self::assertStringContainsString(ReadOnlyLabelNavigationLink::class . 'isnotacomponentclasswithalabelanda', (string) preg_replace('/[\s!]+/', '', $this->display));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function loadableLinkClasses(): iterable
    {
        yield 'a publishable link' => [PublishableNavigationLink::class, true];
        yield 'a link that is not publishable' => [DummyNavigationLink::class, false];
        yield 'a link written through setters' => [SetterNavigationLink::class, false];
    }

    #[DataProvider('loadableLinkClasses')]
    public function test_the_rendered_skeleton_loads_a_labelled_link_to_the_home_route(string $linkClass, bool $published): void
    {
        [$cwa, $persisted] = $this->load($linkClass);

        $links = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof $linkClass));
        self::assertCount(1, $links);
        $link = $links[0];
        self::assertSame('Home', $link instanceof SetterNavigationLink ? $link->getLabel() : $link->label);
        self::assertSame($cwa->getRoute('home'), $link instanceof SetterNavigationLink ? $link->getRoute() : $link->route);
        self::assertSame($published, $link instanceof PublishableNavigationLink && null !== $link->getPublishedAt());
        self::assertCount(1, array_filter($persisted, static fn (object $entity): bool => $entity instanceof Layout));
        self::assertCount(1, array_filter($persisted, static fn (object $entity): bool => $entity instanceof Page));
    }

    public function test_the_rendered_skeleton_without_a_link_loads_a_layout_and_a_home_page(): void
    {
        [$cwa, $persisted] = $this->load('');

        self::assertSame([], array_filter($persisted, static fn (object $entity): bool => $entity instanceof AbstractComponent));
        self::assertCount(1, array_filter($persisted, static fn (object $entity): bool => $entity instanceof Layout));
        self::assertSame('/', $cwa->getRoute('home')->getPath());
    }

    public function test_the_skeleton_has_no_comments_and_uses_the_fluent_form(): void
    {
        $code = $this->render();

        self::assertStringNotContainsString('//', $code);
        self::assertStringNotContainsString('/*', $code);
        self::assertStringNotContainsString('configure:', $code);
        self::assertStringNotContainsString('afterRoutes', $code);
    }

    /**
     * @return array{CwaFixtureBuilder, list<object>}
     */
    private function load(string $linkClass): array
    {
        $code = $this->render(linkClass: $linkClass);
        $namespace = 'App\\DataFixtures\\Skeleton' . bin2hex(random_bytes(6));
        file_put_contents((string) $this->renderedFile, str_replace('namespace App\\DataFixtures;', 'namespace ' . $namespace . ';', $code));
        require (string) $this->renderedFile;

        $persisted = [];
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getAssociationNames')->willReturn([]);
        $metadata->method('getTypeOfField')->willReturn('datetime');
        $manager = $this->createStub(ObjectManager::class);
        $manager->method('getClassMetadata')->willReturn($metadata);
        $manager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $cwa = new CwaFixtureBuilder(
            $this->createStub(TimestampedDataPersister::class),
            $this->createStub(RouteGeneratorInterface::class),
            $this->createStub(IriConverterInterface::class),
            publishableAttributeReader: new PublishableAttributeReader($this->createStub(ManagerRegistry::class)),
        );
        $class = $namespace . '\\AppScaffold';
        $scaffold = new $class($cwa);
        self::assertInstanceOf(AbstractCwaScaffold::class, $scaffold);
        $scaffold->load($manager);

        return [$cwa, $persisted];
    }

    private function render(string $layoutRef = 'main', string $layoutComponent = 'Primary', string $linkClass = PublishableNavigationLink::class): string
    {
        $captured = [];
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')->willReturn(new ClassNameDetails('App\\DataFixtures\\AppScaffold', 'DataFixtures\\'));
        $generator->expects($this->once())->method('generateClass')->willReturnCallback(
            static function (string $className, string $template, array $variables) use (&$captured): string {
                $captured = ['className' => $className, 'template' => $template, 'variables' => $variables];

                return 'src/DataFixtures/AppScaffold.php';
            }
        );

        $maker = new MakeCwaScaffold(new PublishableAttributeReader($this->createStub(ManagerRegistry::class)));
        $command = new Command('make:cwa-scaffold');
        $maker->configureCommand($command, new InputConfiguration());
        $input = new ArrayInput(['name' => 'AppScaffold', '--layout-ref' => $layoutRef, '--layout-component' => $layoutComponent, '--link-class' => $linkClass], $command->getDefinition());
        $input->setInteractive(false);
        $output = new BufferedOutput();
        $maker->generate($input, new ConsoleStyle(new ArrayInput([]), $output), $generator);
        $this->display = $output->fetch();

        $variables = $captured['variables'] + ['namespace' => 'App\\DataFixtures', 'class_name' => 'AppScaffold'];
        $code = (static function (string $template, array $variables): string {
            extract($variables);
            ob_start();
            include $template;

            return (string) ob_get_clean();
        })($captured['template'], $variables);

        $this->renderedFile = tempnam(sys_get_temp_dir(), 'cwa-scaffold-skeleton-') . '.php';
        file_put_contents($this->renderedFile, $code);

        return $code;
    }
}
