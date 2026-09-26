<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedFile;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\WhitespacePathNormalizer;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileReport;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\OrphanedFileReportRecord;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Imagine\FlysystemCacheResolver;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

class OrphanedFileDetector
{
    use ClassMetadataTrait;

    /**
     * @param iterable<object> $cacheResolvers
     * @param list<string>     $excludedPaths
     */
    public function __construct(
        ManagerRegistry $registry,
        private readonly UploadableAttributeReader $uploadableAttributeReader,
        private readonly FilesystemProvider $filesystemProvider,
        private readonly IriConverterInterface $iriConverter,
        private readonly StoredFileLister $lister,
        private readonly iterable $cacheResolvers,
        private readonly array $excludedPaths,
        private readonly int $minimumAge,
    ) {
        $this->initRegistry($registry);
    }

    public function detect(): OrphanedFileReport
    {
        $generatedAt = new \DateTimeImmutable();
        $entityManager = $this->getEntityManager(OrphanedFileReportRecord::class);
        [$prefixes, $references] = $this->readReferences($entityManager);

        $referenced = [];
        foreach ($references as $reference) {
            $referenced[$this->normalize($reference['path'])] = true;
        }

        $excluded = $this->excludedPrefixes();
        $listed = [];
        $orphanedFiles = [];
        $oldest = $generatedAt->getTimestamp() - $this->minimumAge;
        foreach ($prefixes as $adapter => $adapterPrefixes) {
            $filesystem = $this->filesystemProvider->getFilesystem($adapter);
            foreach ($this->lister->list($filesystem, $adapterPrefixes, $excluded) as $file) {
                $path = $file->path();
                $listed[$adapter][$path] = true;
                if (isset($referenced[$path])) {
                    continue;
                }
                $lastModified = $this->lastModified($filesystem, $file);
                if (null === $lastModified || $lastModified > $oldest) {
                    continue;
                }
                $orphanedFiles[] = ['adapter' => $adapter, 'path' => $path];
            }
        }

        $missingFiles = [];
        foreach ($references as $reference) {
            $adapter = $reference['adapter'];
            if (isset($listed[$adapter][$this->normalize($reference['path'])]) || $this->filesystemProvider->getFilesystem($adapter)->fileExists($reference['path'])) {
                continue;
            }
            $missingFiles[] = [
                'resource' => $this->iriConverter->getIriFromResource($entityManager->getReference($reference['class'], $reference['id'])),
                'adapter' => $adapter,
                'path' => $reference['path'],
            ];
        }

        usort($orphanedFiles, static fn (array $a, array $b): int => [$a['adapter'], $a['path']] <=> [$b['adapter'], $b['path']]);
        usort($missingFiles, static fn (array $a, array $b): int => [$a['adapter'], $a['path'], $a['resource']] <=> [$b['adapter'], $b['path'], $b['resource']]);

        return new OrphanedFileReport($generatedAt, $orphanedFiles, $missingFiles);
    }

    /**
     * @return list<string>
     */
    public function scannedAdapters(): array
    {
        $adapters = [];
        foreach ($this->uploadableFields($this->getEntityManager(OrphanedFileReportRecord::class)) as [, $fields]) {
            foreach ($fields as $field) {
                $adapters[$field->adapter] = true;
            }
        }

        return array_keys($adapters);
    }

    /**
     * @return array{array<string, list<string>>, list<array{class: class-string, id: mixed, adapter: string, path: string}>}
     */
    private function readReferences(EntityManagerInterface $entityManager): array
    {
        $prefixes = [];
        $references = [];
        foreach ($this->uploadableFields($entityManager) as [$metadata, $fields]) {
            $class = $metadata->getName();
            $queryBuilder = $entityManager->createQueryBuilder()
                ->select(\sprintf('e.%s AS id', $metadata->getSingleIdentifierFieldName()))
                ->from($class, 'e');
            $hasPath = $queryBuilder->expr()->orX();
            foreach ($fields as $index => $field) {
                $prefixes[$field->adapter][] = $field->prefix ?? '';
                $queryBuilder->addSelect(\sprintf('e.%s AS path%d', $field->property, $index));
                $hasPath->add($queryBuilder->expr()->isNotNull('e.' . $field->property));
            }
            $queryBuilder->where($hasPath);
            if (\count($metadata->subClasses)) {
                $queryBuilder->andWhere(\sprintf('e NOT INSTANCE OF (%s)', implode(', ', $metadata->subClasses)));
            }
            foreach ($queryBuilder->getQuery()->getResult() as $row) {
                foreach ($fields as $index => $field) {
                    $path = $row['path' . $index];
                    if (\is_string($path) && '' !== $path) {
                        $references[] = ['class' => $class, 'id' => $row['id'], 'adapter' => $field->adapter, 'path' => $path];
                    }
                }
            }
        }

        return [$prefixes, $references];
    }

    /**
     * @return iterable<array{ClassMetadata<object>, list<UploadableField>}>
     */
    private function uploadableFields(EntityManagerInterface $entityManager): iterable
    {
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || $metadata->getReflectionClass()->isAbstract() || !$this->uploadableAttributeReader->isConfigured($metadata->getName())) {
                continue;
            }
            yield [$metadata, array_values(iterator_to_array($this->uploadableAttributeReader->getConfiguredProperties($metadata->getName())))];
        }
    }

    /**
     * @return list<string>
     */
    private function excludedPrefixes(): array
    {
        $excluded = $this->excludedPaths;
        foreach ($this->cacheResolvers as $resolver) {
            if ($resolver instanceof FlysystemCacheResolver) {
                $cachePrefix = $resolver->getCachePrefix();
                $excluded[] = '' === $cachePrefix ? '' : rtrim($cachePrefix, '/') . '/';
            }
        }

        return $excluded;
    }

    private function lastModified(Filesystem $filesystem, FileAttributes $file): ?int
    {
        $lastModified = $file->lastModified();
        if (null !== $lastModified) {
            return $lastModified;
        }
        try {
            return $filesystem->lastModified($file->path());
        } catch (FilesystemException) {
            return null;
        }
    }

    private function normalize(string $path): string
    {
        try {
            return (new WhitespacePathNormalizer())->normalizePath($path);
        } catch (FilesystemException) {
            return $path;
        }
    }
}
