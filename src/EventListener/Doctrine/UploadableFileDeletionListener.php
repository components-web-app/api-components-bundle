<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\EventListener\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Symfony\Contracts\Service\ResetInterface;

final class UploadableFileDeletionListener implements ResetInterface
{
    /**
     * @var \WeakMap<EntityManagerInterface, list<array{class-string, UploadableField, string}>>
     */
    private \WeakMap $removedFiles;

    public function __construct(
        private readonly UploadableAttributeReader $uploadableAttributeReader,
        private readonly UploadableFileManager $uploadableFileManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->reset();
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $removedFiles = $this->removedFiles[$entityManager] ?? [];
        foreach ($entityManager->getUnitOfWork()->getScheduledEntityDeletions() as $entity) {
            $classMetadata = $entityManager->getClassMetadata($entity::class);
            $class = $classMetadata->getName();
            if (!$this->uploadableAttributeReader->isConfigured($class)) {
                continue;
            }
            $entityManager->initializeObject($entity);
            foreach ($this->uploadableAttributeReader->getConfiguredProperties($class) as $fieldConfiguration) {
                $path = $classMetadata->getFieldValue($entity, $fieldConfiguration->property);
                if (\is_string($path) && '' !== $path) {
                    $removedFiles[] = [$class, $fieldConfiguration, $path];
                }
            }
        }
        $this->removedFiles[$entityManager] = $removedFiles;
    }

    public function postFlush(PostFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $removedFiles = $this->removedFiles[$entityManager] ?? [];
        unset($this->removedFiles[$entityManager]);

        foreach ($removedFiles as [$class, $fieldConfiguration, $path]) {
            if ($entityManager->getRepository($class)->count([$fieldConfiguration->property => $path]) > 0) {
                continue;
            }
            try {
                $this->uploadableFileManager->deleteStoredFile($fieldConfiguration, $path);
            } catch (FilesystemException $exception) {
                $this->logger?->error('A stored file could not be deleted after the resource holding it was removed.', [
                    'path' => $path,
                    'adapter' => $fieldConfiguration->adapter,
                    'exception' => $exception,
                ]);
            }
        }
    }

    public function reset(): void
    {
        $this->removedFiles = new \WeakMap();
    }
}
