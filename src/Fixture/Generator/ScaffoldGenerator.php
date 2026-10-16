<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ScaffoldRenderer;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ScaffoldPlanner;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\EntityStateReader;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\SiteReader;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;

final readonly class ScaffoldGenerator
{
    private SiteReader $siteReader;

    public function __construct(
        private ManagerRegistry $registry,
        private IriConverterInterface $iriConverter,
        private UploadableAttributeReaderInterface $uploadableAttributeReader,
        private PublishableAttributeReader $publishableAttributeReader,
        private TimestampedAttributeReader $timestampedAttributeReader,
        private FilesystemProvider $filesystemProvider,
    ) {
        $this->siteReader = new SiteReader($registry);
    }

    public function generate(string $namespace, string $className, string $assetsDirectory, bool $keepTimestamps = false, ?\DateTimeImmutable $now = null): GeneratedScaffold
    {
        $imports = new ImportCollector($className);
        $storedFiles = new StoredFileExporter($this->filesystemProvider, $assetsDirectory);
        $planner = new ScaffoldPlanner(
            $this->registry,
            $this->iriConverter,
            new EntityStateReader($this->registry, $this->uploadableAttributeReader, $this->publishableAttributeReader, $this->timestampedAttributeReader),
            $storedFiles,
            $imports,
            new PhpValueExporter(),
            $now ?? new \DateTimeImmutable(),
            $keepTimestamps,
        );
        $plan = $planner->plan($this->siteReader->read());

        return new GeneratedScaffold((new ScaffoldRenderer())->render($plan, $namespace, $className), $plan->notReproduced, $storedFiles->count());
    }
}
