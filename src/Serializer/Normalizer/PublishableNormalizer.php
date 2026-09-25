<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Serializer\Normalizer;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use ApiPlatform\Validator\ValidatorInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Event\ResourceChangedEvent;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Metadata\Provider\PageDataMetadataProvider;
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
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PageDataMetadataProvider $pageDataMetadataProvider,
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
        $locationCount = method_exists($object, 'getComponentPositions')
            ? $this->countLocations($object)
            : null;

        $resourceMetadata = $this->resourceMetadataProvider->findResourceMetadata($object);
        $resourceMetadata->setPublishable($isPublished, null, $locationCount);

        $type = $object::class;

        $configuration = $this->publishableStatusChecker->getAttributeReader()->getConfiguration($type);
        $em = $this->getManagerFromType($type);
        $classMetadata = $em->getClassMetadata($type);

        $publishedAtDateTime = $classMetadata->getFieldValue($object, $configuration->fieldName);
        if ($publishedAtDateTime instanceof \DateTimeInterface) {
            $publishedAtDateTime = $publishedAtDateTime->format(\DateTimeInterface::RFC3339_EXTENDED);
        }

        if ($publishedAtDateTime) {
            $resourceMetadata->setPublishable($isPublished, $publishedAtDateTime);
        }

        if (\is_object($assocObject = $classMetadata->getFieldValue($object, $configuration->associationName))) {
            $context[self::ASSOCIATION] = $assocObject;
        } elseif (\is_object($reverseAssocObject = $classMetadata->getFieldValue($object, $configuration->reverseAssociationName))) {
            $context[self::ASSOCIATION] = $reverseAssocObject;
        }

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

        if (!isset($context[AbstractNormalizer::OBJECT_TO_POPULATE])) {
            if (!$this->publishableStatusChecker->isGranted($type)) {
                $data[$configuration->fieldName] = date('Y-m-d H:i:s');
            }

            return $this->denormalizer->denormalize($data, $type, $format, $context);
        }

        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE];
        $data = $this->setPublishedAt($data, $configuration, $object);

        if (
            empty($data)
            || !$this->publishableStatusChecker->isActivePublishedAt($object)
            || !$this->publishableStatusChecker->isGranted($type)
        ) {
            return $this->denormalizer->denormalize($data, $type, $format, $context);
        }

        $draft = $this->createDraft($object, $configuration, $type);
        $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $draft;

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    private function setPublishedAt(array $data, Publishable $configuration, object $object): array
    {
        if (isset($data[$configuration->fieldName])) {
            $publicationDate = new \DateTimeImmutable($data[$configuration->fieldName]);

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
        unset($data[$configuration->associationName], $data[$configuration->reverseAssociationName]);

        if (!$this->publishableStatusChecker->isGranted($type)) {
            unset($data[$configuration->fieldName]);
        }

        return $data;
    }

    public function createDraft(object $object, Publishable $configuration, string $type): object
    {
        $em = $this->getManagerFromType($type);
        $classMetadata = $em->getClassMetadata($type);

        if (null !== $classMetadata->getFieldValue($object, $configuration->associationName)) {
            return $object;
        }

        $draft = clone $object;

        $classMetadata->setFieldValue($draft, $configuration->fieldName, null);

        $classMetadata->setFieldValue($draft, $configuration->associationName, $object);

        $classMetadata->setFieldValue($object, $configuration->reverseAssociationName, $draft);

        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if (ClassMetadata::ONE_TO_MANY === $mapping['type'] && $this->propertyAccessor->isWritable($draft, $fieldName)) {
                $this->propertyAccessor->setValue($draft, $fieldName, new ArrayCollection());
            }
        }

        try {
            $this->uploadableFileManager->processClonedUploadable($object, $draft);
        } catch (\InvalidArgumentException $e) {
        }
        $em->persist($draft);

        $event = new ResourceChangedEvent($object, 'updated');
        $this->eventDispatcher->dispatch($event);

        return $draft;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $this->publishableStatusChecker->getAttributeReader()->isConfigured($type) && \is_array($data);
    }

    private function countLocations(object $component): int
    {
        $count = $this->registry->getManagerForClass(ComponentPosition::class)
            ?->getRepository(ComponentPosition::class)->count(['component' => $component])
            ?? 0;

        foreach ($this->pageDataMetadataProvider->createAll() as $pageDataMetadata) {
            $pageDataClass = $pageDataMetadata->getResourceClass();
            $em = $this->registry->getManagerForClass($pageDataClass);
            if (!$em) {
                continue;
            }
            foreach ($pageDataMetadata->getProperties() as $propertyMetadata) {
                if (!is_a($component, $propertyMetadata->getComponentClass(), true)) {
                    continue;
                }
                $count += $em->getRepository($pageDataClass)->count([$propertyMetadata->getProperty() => $component]);
            }
        }

        return $count;
    }

    private function getManagerFromType(string $type): EntityManagerInterface
    {
        $em = $this->registry->getManagerForClass($type);
        if (!$em instanceof EntityManagerInterface) {
            throw new InvalidArgumentException(\sprintf('Could not find entity manager for class %s', $type));
        }

        return $em;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }
}
