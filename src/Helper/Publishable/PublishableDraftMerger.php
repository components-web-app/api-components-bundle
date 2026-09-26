<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\Publishable;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 * @author Daniel West <daniel@silverback.is>
 */
final class PublishableDraftMerger
{
    use ClassMetadataTrait;

    private readonly PublishableAttributeReader $publishableAttributeReader;

    public function __construct(
        private readonly PublishableStatusChecker $publishableStatusChecker,
        ManagerRegistry $registry,
        private readonly UploadableFileManager $uploadableFileManager,
    ) {
        $this->publishableAttributeReader = $publishableStatusChecker->getAttributeReader();
        $this->initRegistry($registry);
    }

    public function mergeDueDraft(Request $request, object $data, bool $flushDatabase = false): object
    {
        if (!$this->publishableStatusChecker->isActivePublishedAt($data)) {
            return $data;
        }

        $configuration = $this->publishableAttributeReader->getConfiguration($data);
        $classMetadata = $this->getClassMetadata($data);

        $publishedResourceAssociation = $classMetadata->getFieldValue($data, $configuration->associationName);
        $draftResourceAssociation = $classMetadata->getFieldValue($data, $configuration->reverseAssociationName);
        if (
            !$publishedResourceAssociation
            && (!$draftResourceAssociation || !$this->publishableStatusChecker->isActivePublishedAt($draftResourceAssociation))
        ) {
            return $data;
        }

        $entityManager = $this->getEntityManager($data);

        $meta = $entityManager->getClassMetadata($data::class);
        $identifierFieldName = $meta->getSingleIdentifierFieldName();

        if ($publishedResourceAssociation) {
            $draftResource = $data;
            $publishedResource = $publishedResourceAssociation;

            $publishedId = $classMetadata->getFieldValue($publishedResource, $identifierFieldName);
            $request->attributes->set('id', $publishedId);
            $request->attributes->set('data', $publishedResource);
            $request->attributes->set('previous_data', clone $publishedResource);
        } else {
            $publishedResource = $data;
            $draftResource = $draftResourceAssociation;
        }

        $classMetadata->setFieldValue($publishedResource, $configuration->reverseAssociationName, null);
        $classMetadata->setFieldValue($draftResource, $configuration->associationName, null);

        $this->mergeDraftIntoPublished($identifierFieldName, $draftResource, $publishedResource, $flushDatabase);

        return $publishedResource;
    }

    private function mergeDraftIntoPublished(string $identifierFieldName, object $draftResource, object $publishedResource, bool $flushDatabase): void
    {
        $draftReflection = new \ReflectionClass($draftResource);
        $publishedReflection = new \ReflectionClass($publishedResource);
        $properties = $publishedReflection->getProperties();

        $previousFilePaths = $this->uploadableFileManager->getStoredFilePaths($publishedResource);

        foreach ($properties as $property) {
            $name = $property->getName();
            if ($identifierFieldName === $name) {
                continue;
            }
            $draftProperty = $draftReflection->hasProperty($name) ? $draftReflection->getProperty($name) : null;
            if ($draftProperty) {
                $draftValue = $draftProperty->getValue($draftResource);
                $property->setValue($publishedResource, $draftValue);
            }
        }

        $this->uploadableFileManager->transferDeletedFields($draftResource, $publishedResource);

        $this->uploadableFileManager->deleteOrphanedFiles($publishedResource, $previousFilePaths);

        $entityManager = $this->getEntityManager($draftResource);
        $entityManager->remove($draftResource);

        if ($flushDatabase) {
            $entityManager->flush();
        }
    }
}
