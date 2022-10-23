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

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use ApiPlatform\Validator\ValidatorInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PurgeHttpCacheListener;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Mercure\MercureResourcePublisher;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataInterface;
use Silverback\ApiComponentsBundle\Validator\PublishableValidator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
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
final class PublishableNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface, DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'PUBLISHABLE_NORMALIZER_ALREADY_CALLED';
    private const ASSOCIATION = 'PUBLISHABLE_ASSOCIATION';
    public const DRAFT_CREATED = 'DRAFT_CREATED';

    private PropertyAccessor $propertyAccessor;

    public function __construct(
        private PublishableStatusChecker $publishableStatusChecker,
        private ManagerRegistry $registry,
        private RequestStack $requestStack,
        private ValidatorInterface $validator,
        private IriConverterInterface $iriConverter,
        private UploadableFileManager $uploadableFileManager,
        private ResourceMetadataInterface $resourceMetadata,
        private ?PurgeHttpCacheListener $purgeHttpCacheListener = null,
        private ?MercureResourcePublisher $mercureResourcePublisher = null
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
        $this->resourceMetadata->setPublishable($isPublished);

        $type = \get_class($object);
        $configuration = $this->publishableStatusChecker->getAnnotationReader()->getConfiguration($type);
        $em = $this->getManagerFromType($type);
        $classMetadata = $this->getClassMetadataInfo($em, $type);

        $publishedAtDateTime = $classMetadata->getFieldValue($object, $configuration->fieldName);
        if ($publishedAtDateTime instanceof \DateTimeInterface) {
            $publishedAtDateTime = $publishedAtDateTime->format(\DateTimeInterface::RFC3339_EXTENDED);
        }

        // using static name 'publishedAt' for predictable API and easy metadata object instead of dynamic $configuration->fieldName
        if ($publishedAtDateTime) {
            $this->resourceMetadata->setPublishable($isPublished, $publishedAtDateTime);
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
                $this->resourceMetadata->setViolationList($exception->getConstraintViolationList());
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

        return !\in_array($id, $context[self::ALREADY_CALLED], true) &&
            $this->publishableStatusChecker->getAnnotationReader()->isConfigured($data);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_CALLED] = true;
        $configuration = $this->publishableStatusChecker->getAnnotationReader()->getConfiguration($type);

        $data = $this->unsetRestrictedData($type, $data, $configuration);

        $request = $this->requestStack->getMainRequest();
        if ($request && true === $this->publishableStatusChecker->isPublishedRequest($request)) {
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
            empty($data) ||
            !$this->publishableStatusChecker->isActivePublishedAt($object) ||
            !$this->publishableStatusChecker->isGranted($type)
        ) {
            return $this->denormalizer->denormalize($data, $type, $format, $context);
        }

        // Any field has been modified: create a draft
        $draft = $this->createDraft($object, $configuration, $type);
        $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $draft;
        $context[self::DRAFT_CREATED] = true;

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    private function setPublishedAt(array $data, Publishable $configuration, object $object): array
    {
        if (isset($data[$configuration->fieldName])) {
            $publicationDate = new \DateTimeImmutable($data[$configuration->fieldName]);

            // User changed the publication date with an earlier one on a published resource: ignore it
            if (
                $this->publishableStatusChecker->isActivePublishedAt($object) &&
                new \DateTimeImmutable() >= $publicationDate
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

        try {
            $this->uploadableFileManager->processClonedUploadable($object, $draft);
        } catch (\InvalidArgumentException $e) {
            // ok exception, it may not be uploadable...
        }

        // Add draft object to UnitOfWork
        $em->persist($draft);

        // Clear the cache of the published resource because it should now also return an associated draft
        if ($this->purgeHttpCacheListener) {
            $this->purgeHttpCacheListener->addTagsFor($object);
        }

        if ($this->mercureResourcePublisher) {
            $this->mercureResourcePublisher->publishResourceUpdate($object);

            if ($object instanceof AbstractComponent) {
                foreach ($object->getComponentPositions() as $componentPosition) {
                    $this->mercureResourcePublisher->publishResourceUpdate($componentPosition);
                }
            }
        }

        return $draft;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $this->publishableStatusChecker->getAnnotationReader()->isConfigured($type);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
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
}
