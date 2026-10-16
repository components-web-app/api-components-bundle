<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Plan;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\NotReproducedReport;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\PropertyValueWriter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\PropertyValue;
use Silverback\ApiComponentsBundle\Fixture\Generator\StoredFileExporter;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\File;

class PropertyValueWriterTest extends TestCase
{
    use PlannerTestTrait;

    private NotReproducedReport $report;
    private ImportCollector $imports;
    private string $assets;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->assets = sys_get_temp_dir() . '/cwa-property-value-writer-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->assets . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->assets)) {
            rmdir($this->assets);
        }
    }

    public function test_a_field_is_written_as_php(): void
    {
        $value = new PropertyValue(PropertyValue::FIELD, 'uiClassNames', ['a', "b\nc"], true);

        self::assertSame('[\'a\', "b\\nc"]', $this->writer()->expression(new DummyComponent(), $value));
        self::assertSame([], $this->report->all());
    }

    public function test_a_field_that_cannot_be_written_as_php_is_reported(): void
    {
        $value = new PropertyValue(PropertyValue::FIELD, 'uiClassNames', [new \stdClass()], true);

        self::assertNull($this->writer()->expression(new DummyComponent(), $value));
        self::assertSame(['The value of ' . DummyComponent::class . '::$uiClassNames cannot be written as PHP (1 component).'], $this->report->all());
    }

    public function test_a_stored_file_is_exported_and_loaded_back_as_a_file(): void
    {
        $this->filesystem->write('components/photo-0a1b2c3d.png', 'image');
        $value = new PropertyValue(PropertyValue::FILE, 'file', ['adapter' => 'local', 'path' => 'components/photo-0a1b2c3d.png'], true);

        self::assertSame("new File(__DIR__ . '/assets/photo.png')", $this->writer()->expression(new DummyComponent(), $value));
        self::assertSame('image', file_get_contents($this->assets . '/photo.png'));
        self::assertContains('use ' . File::class . ';', $this->imports->lines());
    }

    public function test_a_stored_file_that_cannot_be_read_is_reported(): void
    {
        $value = new PropertyValue(PropertyValue::FILE, 'file', ['adapter' => 'local', 'path' => 'components/missing.png'], true);

        self::assertNull($this->writer()->expression(new DummyComponent(), $value));
        self::assertCount(1, $this->report->all());
        self::assertStringStartsWith('The stored file "components/missing.png" could not be read: ', $this->report->all()[0]);
        self::assertDirectoryDoesNotExist($this->assets);
    }

    public function test_a_collection_is_reported(): void
    {
        $value = new PropertyValue(PropertyValue::COLLECTION, 'items', [new DummyComponent()], true);

        self::assertNull($this->writer()->expression(new DummyComponent(), $value));
        self::assertSame(['The collection ' . DummyComponent::class . '::$items (1 component).'], $this->report->all());
    }

    public function test_a_reference_outside_the_site_is_reported_with_what_it_refers_to(): void
    {
        $layout = new Layout();
        $layout->reference = 'other';
        $writer = $this->writer();

        self::assertNull($writer->expression(new DummyComponent(), new PropertyValue(PropertyValue::ENTITY, 'layout', $layout, true)));
        self::assertNull($writer->expression(new DummyComponent(), new PropertyValue(PropertyValue::ROUTE, 'route', 'detached', true)));
        self::assertSame([
            DummyComponent::class . '::$layout refers to /_/layouts/other, which is not part of the generated site.',
            DummyComponent::class . '::$route refers to string, which is not part of the generated site.',
        ], $this->report->all());
    }

    public function test_a_value_with_no_property_or_setter_is_reported(): void
    {
        $this->writer()->reportUnwritable(new DummyComponent(), new PropertyValue(PropertyValue::FIELD, 'secret', 'x', false));

        self::assertSame([DummyComponent::class . '::$secret has no public property or setter to write it with (1 component).'], $this->report->all());
    }

    private function writer(): PropertyValueWriter
    {
        $this->report = new NotReproducedReport($this->iriConverter());
        $this->imports = new ImportCollector('SiteScaffold');
        $storedFiles = new StoredFileExporter(new FilesystemProvider(new ServiceLocator(['local' => fn () => $this->filesystem])), $this->assets);

        return new PropertyValueWriter($this->stateReader($this->registry()), $storedFiles, $this->imports, new PhpValueExporter(), $this->report);
    }
}
