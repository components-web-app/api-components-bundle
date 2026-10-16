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
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetLoader;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetManifest;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStoreFactory;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CwaFixtureBuilderAssetTest extends TestCase
{
    private string $directory;
    private string $hash;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/cwa-fixture-builder-asset-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/assets', 0777, true);
        $this->hash = hash('sha256', 'hello');
        $manifest = new FixtureAssetManifest();
        $manifest->add($this->hash, 'greeting.txt', 'text/plain', 5, 'App\\Entity\\Doc::file');
        file_put_contents(FixtureAssetManifest::pathFor($this->directory, 'AssetScaffold'), $manifest->toJson());
        file_put_contents($this->directory . '/assets/' . $this->hash, 'hello');
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function test_a_scaffold_loads_its_assets_through_the_manifest_beside_its_class(): void
    {
        $scaffold = $this->scaffold($this->builder($this->loader()));

        $scaffold->load($this->createStub(ObjectManager::class));

        self::assertInstanceOf(UploadedFile::class, $scaffold->file);
        self::assertSame('greeting.txt', $scaffold->file->getClientOriginalName());
        self::assertSame('hello', file_get_contents($scaffold->file->getRealPath()));
        self::assertInstanceOf(UploadedFile::class, $scaffold->renamed);
        self::assertSame('copy.txt', $scaffold->renamed->getClientOriginalName());
    }

    public function test_the_builder_needs_the_manifest_to_load_an_asset(): void
    {
        $builder = $this->builder($this->loader());
        $builder->withManager($this->createStub(ObjectManager::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('asset() is only available while an AbstractCwaScaffold loads, which tells the builder where its asset manifest is.');

        $builder->asset($this->hash);
    }

    public function test_the_builder_needs_an_asset_loader(): void
    {
        $builder = $this->builder(null);
        $builder->withManager($this->createStub(ObjectManager::class))->withAssetManifest(FixtureAssetManifest::pathFor($this->directory, 'AssetScaffold'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('asset() needs the fixture asset loader. Pass it to the CwaFixtureBuilder.');

        $builder->asset($this->hash);
    }

    public function test_starting_the_next_load_releases_the_copies_and_forgets_the_manifest(): void
    {
        $builder = $this->builder($this->loader());
        $builder->withManager($this->createStub(ObjectManager::class))->withAssetManifest(FixtureAssetManifest::pathFor($this->directory, 'AssetScaffold'));
        $copy = $builder->asset($this->hash)->getRealPath();

        $builder->withManager($this->createStub(ObjectManager::class));

        self::assertFileDoesNotExist($copy);
        $this->expectException(\LogicException::class);
        $builder->asset($this->hash);
    }

    private function loader(): FixtureAssetLoader
    {
        return new FixtureAssetLoader(new FixtureAssetStoreFactory(new FilesystemProvider(new ServiceLocator([]))));
    }

    private function builder(?FixtureAssetLoader $loader): CwaFixtureBuilder
    {
        return new CwaFixtureBuilder(
            $this->createStub(TimestampedDataPersister::class),
            $this->createStub(RouteGeneratorInterface::class),
            $this->createStub(IriConverterInterface::class),
            null,
            null,
            null,
            null,
            null,
            $loader,
        );
    }

    private function scaffold(CwaFixtureBuilder $builder): object
    {
        $namespace = 'Silverback\\ApiComponentsBundle\\Tests\\Fixture\\Generated' . bin2hex(random_bytes(6));
        $code = <<<PHP
            <?php

            namespace {$namespace};

            use Silverback\\ApiComponentsBundle\\Fixture\\AbstractCwaScaffold;
            use Silverback\\ApiComponentsBundle\\Fixture\\CwaFixtureBuilder;

            class AssetScaffold extends AbstractCwaScaffold
            {
                public ?object \$file = null;
                public ?object \$renamed = null;

                public function build(CwaFixtureBuilder \$cwa): void
                {
                    \$this->file = \$cwa->asset('{$this->hash}');
                    \$this->renamed = \$cwa->asset('{$this->hash}', name: 'copy.txt');
                }
            }

            PHP;
        $file = $this->directory . '/AssetScaffold.php';
        file_put_contents($file, $code);
        require $file;
        $class = $namespace . '\\AssetScaffold';

        return new $class($builder);
    }
}
