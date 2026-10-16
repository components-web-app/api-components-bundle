<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator\Plan;

use League\Flysystem\FilesystemException;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\EntityStateReader;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\PropertyValue;
use Silverback\ApiComponentsBundle\Fixture\Generator\StoredFileExport;

/**
 * @internal
 */
final readonly class PropertyValueWriter
{
    public function __construct(
        private EntityStateReader $stateReader,
        private StoredFileExport $storedFileExporter,
        private ImportCollector $imports,
        private PhpValueExporter $exporter,
        private NotReproducedReport $report,
    ) {
    }

    public function expression(object $entity, PropertyValue $value): ?string
    {
        switch ($value->kind) {
            case PropertyValue::FIELD:
                $expression = $this->exporter->export($value->value, $this->imports);
                if (null === $expression) {
                    $this->report->addForEach(\sprintf('The value of %s::$%s cannot be written as PHP.', $this->stateReader->className($entity), $value->property), $entity);
                }

                return $expression;
            case PropertyValue::FILE:
                /** @var array{adapter: string, path: string} $file */
                $file = $value->value;
                try {
                    return $this->storedFileExporter->expression($file['adapter'], $file['path'], $this->stateReader->className($entity) . '::' . $value->property, $this->imports);
                } catch (FilesystemException $exception) {
                    $this->report->add(\sprintf('The stored file "%s" could not be read: %s', $file['path'], $exception->getMessage()));

                    return null;
                }
            case PropertyValue::COLLECTION:
                $this->report->addForEach(\sprintf('The collection %s::$%s.', $this->stateReader->className($entity), $value->property), $entity);

                return null;
            default:
                $this->report->add(\sprintf('%s::$%s refers to %s, which is not part of the generated site.', $this->stateReader->className($entity), $value->property, \is_object($value->value) ? $this->report->describe($value->value) : get_debug_type($value->value)));

                return null;
        }
    }

    public function reportUnwritable(object $entity, PropertyValue $value): void
    {
        $this->report->addForEach(\sprintf('%s::$%s has no public property or setter to write it with.', $this->stateReader->className($entity), $value->property), $entity);
    }
}
