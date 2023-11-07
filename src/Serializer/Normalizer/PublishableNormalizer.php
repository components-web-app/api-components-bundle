<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Serializer\Normalizer;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use ApiPlatform\Validator\ValidatorInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\Event\ResourceChangedEvent;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Validator\PublishableValidator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Adds `published` property on response, if not set.
 *
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableNormalizer implements NormalizerInterface, NormalizerAwareInterface, DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'PUBLISHABLE_NORMALIZER_ALREADY_CALLED';
    private const ASSOCIATION = 'PUBLISHABLE_ASSOCIATION';

    private PropertyAccessor $propertyAccessor;

    public function __construct(
        private readonly PublishableStatusChecker $publishableStatusChecker,
        private readonly ManagerRegistry $registry,
        private readonly RequestStack $requestStack,
        private readonly ValidatorInterface $validator,
        private readonly IriConverterInterface $iriConverter,
        private readonly UploadableFileManager $uploadableFileManager,
        private readonly ResourceMetadataProvider $resourceMetadataProvider,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        $context[self::ALREADY_CALLED][] = $this->propertyAccessor->getValue($object, 'id');

        if (isset($context[self::ASSOCIATION]) && $context[self::ASSOCIATION] === $object) {
            return $this->iriConverter->getIriFromResource($object);
        }

        $isPublished = $this->publishableStatusChecker->isActivePublishedAt($object);

        $resourceMetadata = $this->resourceMetadataProvider->findResourceMetadata($object);
        $resourceMetadata->setPublishable($isPublished);

        $type = $object::class;
        $configuration = $this->publishableStatusChecker->getAttributeReader()->getConfiguration($type);
        $em = $this->getManagerFromType($type);
        $classMetadata = $this->getClassMetadataInfo($em, $type);

        $publishedAtDateTime = $classMetadata->getFieldValue($object, $configuration->fieldName);
        if ($publishedAtDateTime instanceof \DateTimeInterface) {
            $publishedAtDateTime = $publishedAtDateTime->format(\DateTimeInterface::RFC3339_EXTENDED);
        }

        // using static name 'publishedAt' for predictable API and easy metadata object instead of dynamic $configuration->fieldName
        if ($publishedAtDateTime) {
            $resourceMetadata->setPublishable($isPublished, $publishedAtDateTime);
        }

        if (\is_object($assocObject = $classMetadata->getFieldValue($object, $configuration->associationName))) {
            $context[self::ASSOCIATION] = $assocObject;
        } elseif (\is_object($reverseAssocObject = $classMetadata->getFieldValue($object, $configuration->reverseAssociationName))) {
            $context[self::ASSOCIATION] = $reverseAssocObject;
        }

        // display soft validation violations in the response
        if ($this->publishableStatusChecker->isGranted($object)) {
            try {
                $this->validator->validate($object, [PublishableValidator::PUBLISHED_KEY => true]);
            } catch (ValidationException $exception) {
                $resourceMetadata->setViolations($exception->getConstraintViolationList());
            }
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        if (!\is_object($data) || $data instanceof \Traversable) {
            return false;
        }
        if (!isset($context[self::ALREADY_CALLED])) {
            $context[self::ALREADY_CALLED] = [];
        }
        try {
            $id = $this->propertyAccessor->getValue($data, 'id');
        } catch (NoSuchPropertyException $e) {
            return false;
        }

        return !\in_array($id, $context[self::ALREADY_CALLED], true)
            && $this->publishableStatusChecker->getAttributeReader()->isConfigured($data);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_CALLED] = true;
        $configuration = $this->publishableStatusChecker->getAttributeReader()->getConfiguration($type);

        $data = $this->unsetRestrictedData($type, $data, $configuration);

        $request = $this->requestStack->getMainRequest();
        if ($request && true === $this->publishableStatusChecker->isRequestForPublished($request)) {
            return $this->denormalizer->denormalize($data, $type, $format, $context);
        }

        // It's a new object
        if (!isset($context[AbstractNormalizer::OBJECT_TO_POPULATE])) {
            // User doesn't have draft access: force publication date
            if (!$this->publishableStatusChecker->isGranted($type)) {
                $data[$configuration->fieldName] = date('Y-m-d H:i:s');
            }

            return $this->denormalizer->denormalize($data, $type, $format, $context);
        }

        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE];
        $data = $this->setPublishedAt($data, $configuration, $object);

        // No field has been updated (after publishedAt verified and cleaned/unset if needed): nothing to do here anymore
        // or User doesn't have draft access: update the original object
        if (
            empty($data)
            || !$this->publishableStatusChecker->isActivePublishedAt($object)
            || !$this->publishableStatusChecker->isGranted($type)
        ) {
            return $this->denormalizer->denormalize($data, $type, $format, $context);
        }

        // Any field has been modified: create a draft
        $draft = $this->createDraft($object, $configuration, $type);
        $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $draft;

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    private function setPublishedAt(array $data, Publishable $configuration, object $object): array
    {
        if (isset($data[$configuration->fieldName])) {
            $publicationDate = new \DateTimeImmutable($data[$configuration->fieldName]);

            // User changed the publication date with an earlier one on a published resource: ignore it
            if (
                $this->publishableStatusChecker->isActivePublishedAt($object)
                && new \DateTimeImmutable() >= $publicationDate
            ) {
                unset($data[$configuration->fieldName]);
            }
        }

        return $data;
    }

    private function unsetRestrictedData($type, array $data, Publishable $configuration): array
    {
        // It's not possible to change the publishedResource and draftResource properties
        unset($data[$configuration->associationName], $data[$configuration->reverseAssociationName]);

        // User doesn't have draft access: cannot set or change the publication date
        if (!$this->publishableStatusChecker->isGranted($type)) {
            unset($data[$configuration->fieldName]);
        }

        return $data;
    }

    public function createDraft(object $object, Publishable $configuration, string $type): object
    {
        $em = $this->getManagerFromType($type);
        $classMetadata = $this->getClassMetadataInfo($em, $type);

        // Resource is a draft: nothing to do here anymore
        if (null !== $classMetadata->getFieldValue($object, $configuration->associationName)) {
            return $object;
        }

        $draft = clone $object; // Identifier(s) should be reset from AbstractComponent::__clone method

        // Empty publishedDate on draft
        $classMetadata->setFieldValue($draft, $configuration->fieldName, null);

        // Set publishedResource on draft
        $classMetadata->setFieldValue($draft, $configuration->associationName, $object);

        // Set draftResource on data if we have permission
        $classMetadata->setFieldValue($object, $configuration->reverseAssociationName, $draft);

        // Clear any writable one-to-many fields, these should still reference the published component, such as component positions
        // Doesn't matter usually it seems, but where we process uploadable, the one-to-many is not then reassigned later back to the publishable during normalization
        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if (ClassMetadataInfo::ONE_TO_MANY === $mapping['type'] && $this->propertyAccessor->isWritable($draft, $fieldName)) {
                $this->propertyAccessor->setValue($draft, $fieldName, new ArrayCollection());
            }
        }

        try {
            $this->uploadableFileManager->processClonedUploadable($object, $draft);
        } catch (\InvalidArgumentException $e) {
            // ok exception, it may not be uploadable...
        }
        // Add draft object to UnitOfWork
        $em->persist($draft);

        // Clear the cache of the published resource because it should now also return an associated draft
        $event = new ResourceChangedEvent($object, 'updated');
        $this->eventDispatcher->dispatch($event);

        return $draft;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $this->publishableStatusChecker->getAttributeReader()->isConfigured($type);
    }

    private function getManagerFromType(string $type): ObjectManager
    {
        $em = $this->registry->getManagerForClass($type);
        if (!$em) {
            throw new InvalidArgumentException(sprintf('Could not find entity manager for class %s', $type));
        }

        return $em;
    }

    private function getClassMetadataInfo(ObjectManager $em, string $type): ClassMetadataInfo
    {
        $classMetadata = $em->getClassMetadata($type);
        if (!$classMetadata instanceof ClassMetadataInfo) {
            throw new InvalidArgumentException(sprintf('Class metadata for %s was not an instance of %s', $type, ClassMetadataInfo::class));
        }

        return $classMetadata;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }
}
