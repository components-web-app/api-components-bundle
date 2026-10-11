<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\Uploadable;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Entity\Utility\ImagineFiltersInterface;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Imagine\ImagineFilterGenerator;
use Silverback\ApiComponentsBundle\Imagine\ImagineFilterOutcome;
use Silverback\ApiComponentsBundle\Model\Uploadable\ImagineVariant;
use Silverback\ApiComponentsBundle\Model\Uploadable\ImagineVariantStatus;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

final class ImagineVariantGenerator
{
    use ClassMetadataTrait;

    public function __construct(
        ManagerRegistry $registry,
        private readonly UploadableAttributeReader $uploadableAttributeReader,
        private readonly FilesystemProvider $filesystemProvider,
        private readonly FlysystemDataLoader $flysystemDataLoader,
        private readonly FileInfoCacheManager $fileInfoCacheManager,
        private readonly UploadableCacheControl $cacheControl,
        private readonly ImagineFilterGenerator $imagineFilterGenerator,
        private readonly int $batchSize,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->initRegistry($registry);
    }

    /**
     * @return list<class-string>
     */
    public function uploadableClasses(): array
    {
        $classes = [];
        foreach ($this->uploadableMetadata($this->getEntityManager(FileInfo::class)) as $metadata) {
            $classes[] = $metadata->getName();
        }

        return $classes;
    }

    /**
     * @param list<class-string> $classes
     * @param list<string>       $filters
     *
     * @return \Generator<int, ImagineVariant>
     */
    public function generate(array $classes = [], array $filters = [], bool $dryRun = false): \Generator
    {
        $entityManager = $this->getEntityManager(FileInfo::class);
        foreach ($this->uploadableMetadata($entityManager) as $metadata) {
            if ($classes && !array_filter($classes, static fn (string $class): bool => is_a($metadata->getName(), $class, true))) {
                continue;
            }
            yield from $this->generateForClass($entityManager, $metadata, $filters, $dryRun);
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<string>          $filters
     *
     * @return \Generator<int, ImagineVariant>
     */
    private function generateForClass(EntityManagerInterface $entityManager, ClassMetadata $metadata, array $filters, bool $dryRun): \Generator
    {
        $fields = iterator_to_array($this->uploadableAttributeReader->getConfiguredProperties($metadata->getName()));
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('e')
            ->from($metadata->getName(), 'e')
            ->orderBy('e.' . $metadata->getSingleIdentifierFieldName())
            ->setMaxResults($this->batchSize);
        $hasPath = $queryBuilder->expr()->orX();
        foreach ($fields as $field) {
            $hasPath->add($queryBuilder->expr()->isNotNull('e.' . $field->property));
        }
        $queryBuilder->where($hasPath);
        if (\count($metadata->subClasses)) {
            $queryBuilder->andWhere(\sprintf('e NOT INSTANCE OF (%s)', implode(', ', $metadata->subClasses)));
        }

        $offset = 0;
        do {
            $objects = $queryBuilder->setFirstResult($offset)->getQuery()->getResult();
            foreach ($objects as $object) {
                foreach ($fields as $fileProperty => $field) {
                    yield from $this->generateForField($metadata, $object, $fileProperty, $field, $filters, $dryRun);
                }
            }
            $entityManager->clear();
            $offset += $this->batchSize;
        } while (\count($objects) === $this->batchSize);
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<string>          $onlyFilters
     *
     * @return \Generator<int, ImagineVariant>
     */
    private function generateForField(ClassMetadata $metadata, object $object, string $fileProperty, UploadableField $field, array $onlyFilters, bool $dryRun): \Generator
    {
        $path = $metadata->getFieldValue($object, $field->property);
        if (!\is_string($path) || '' === $path) {
            return;
        }
        $filters = $field->imagineFilters ?? [];
        if ($object instanceof ImagineFiltersInterface) {
            array_push($filters, ...$object->getImagineFilters($fileProperty, null));
        }
        $filters = array_unique($filters);
        if ($onlyFilters) {
            $filters = array_intersect($filters, $onlyFilters);
        }
        if (!$filters) {
            return;
        }

        $variant = static fn (string $filter, ImagineVariantStatus $status): ImagineVariant => new ImagineVariant($metadata->getName(), $fileProperty, $path, $filter, $status);
        $filesystem = $this->filesystemProvider->getFilesystem($field->adapter);
        try {
            $exists = $filesystem->fileExists($path);
            $fileInfo = $exists ? $this->fileInfoCacheManager->resolveCache($path) : null;
            $mimeType = $exists ? ($fileInfo->mimeType ?? $filesystem->mimeType($path)) : null;
        } catch (FilesystemException $exception) {
            yield from $this->unreadableOriginal($variant, $path, $filters, $exception);

            return;
        }
        if (null === $mimeType) {
            foreach ($filters as $filter) {
                yield $variant($filter, ImagineVariantStatus::OriginalMissing);
            }

            return;
        }
        if (!str_starts_with($mimeType, 'image/') || 'image/svg+xml' === $mimeType) {
            return;
        }

        $pending = [];
        foreach ($filters as $filter) {
            try {
                $stored = $this->imagineFilterGenerator->isStored($path, $filter);
            } catch (\Exception $exception) {
                $this->logger?->error(\sprintf('Could not check whether the imagine filter "%s" for "%s" is stored: %s', $filter, $path, $exception->getMessage()), [
                    'filter' => $filter,
                    'path' => $path,
                    'exception' => $exception,
                ]);
                yield $variant($filter, ImagineVariantStatus::Failed);
                continue;
            }
            if ($stored) {
                yield $variant($filter, ImagineVariantStatus::Stored);
                continue;
            }
            if ($dryRun) {
                yield $variant($filter, ImagineVariantStatus::Pending);
                continue;
            }
            $pending[] = $filter;
        }
        if (!$pending) {
            return;
        }

        try {
            [$width, $height] = $fileInfo ? [$fileInfo->width, $fileInfo->height] : $this->readDimensions($filesystem, $path);
        } catch (FilesystemException $exception) {
            yield from $this->unreadableOriginal($variant, $path, $pending, $exception);

            return;
        }
        $this->flysystemDataLoader->setAdapter($field->adapter);
        $this->cacheControl->registerVariantSource($path, $field);
        foreach ($pending as $filter) {
            yield $variant($filter, match ($this->imagineFilterGenerator->filter($path, $filter, $width, $height)->outcome) {
                ImagineFilterOutcome::Resolved => ImagineVariantStatus::Generated,
                ImagineFilterOutcome::Skipped => ImagineVariantStatus::Skipped,
                ImagineFilterOutcome::Failed => ImagineVariantStatus::Failed,
            });
        }
    }

    /**
     * @return array{?int, ?int}
     */
    private function readDimensions(Filesystem $filesystem, string $path): array
    {
        $size = @getimagesizefromstring($filesystem->read($path));

        return $size ? [$size[0], $size[1]] : [null, null];
    }

    /**
     * @param \Closure(string, ImagineVariantStatus): ImagineVariant $variant
     * @param array<string>                                          $filters
     *
     * @return \Generator<int, ImagineVariant>
     */
    private function unreadableOriginal(\Closure $variant, string $path, array $filters, FilesystemException $exception): \Generator
    {
        $this->logger?->error(\sprintf('The imagine filters for "%s" could not be generated because the original could not be read: %s', $path, $exception->getMessage()), [
            'path' => $path,
            'exception' => $exception,
        ]);
        foreach ($filters as $filter) {
            yield $variant($filter, ImagineVariantStatus::Failed);
        }
    }

    /**
     * @return iterable<ClassMetadata<object>>
     */
    private function uploadableMetadata(EntityManagerInterface $entityManager): iterable
    {
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || $metadata->getReflectionClass()->isAbstract() || !$this->uploadableAttributeReader->isConfigured($metadata->getName())) {
                continue;
            }
            yield $metadata;
        }
    }
}
