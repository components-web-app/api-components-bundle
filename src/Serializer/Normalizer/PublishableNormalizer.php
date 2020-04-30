<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Serializer\Normalizer;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use ApiPlatform\Core\Validator\ValidatorInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Annotation\Publishable;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Helper\PublishableHelper;
use Silverback\ApiComponentBundle\Validator\PublishableValidator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * Adds `published` property on response, if not set.
 *
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableNormalizer implements ContextAwareNormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface, ContextAwareDenormalizerInterface, DenormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'PUBLISHABLE_NORMALIZER_ALREADY_CALLED';

    private PublishableHelper $publishableHelper;
    private ManagerRegistry $registry;
    private RequestStack $requestStack;
    private ValidatorInterface $validator;

    public function __construct(PublishableHelper $publishableHelper, ManagerRegistry $registry, RequestStack $requestStack, ValidatorInterface $validator)
    {
        $this->publishableHelper = $publishableHelper;
        $this->registry = $registry;
        $this->requestStack = $requestStack;
        $this->validator = $validator;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;
        $context[MetadataNormalizer::METADATA_CONTEXT]['published'] = $this->publishableHelper->isActivePublishedAt($object);

        try {
            $this->validator->validate($object, [PublishableValidator::PUBLISHED_KEY => true]);
        } catch (ValidationException $exception) {
            $context[MetadataNormalizer::METADATA_CONTEXT]['violation_list'] = $this->normalizer->normalize($exception->getConstraintViolationList(), $format);
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) &&
            \is_object($data) &&
            !$data instanceof \Traversable &&
            $this->publishableHelper->isConfigured($data);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;
        $configuration = $this->publishableHelper->getConfiguration($type);

        $data = $this->unsetRestrictedData($data, $configuration);

        $request = $this->requestStack->getMasterRequest();
        if ($request && true === $this->publishableHelper->isPublishedRequest($request)) {
            return $this->denormalizer->denormalize($data, $type, $format, $context);
        }

        // It's a new object
        if (!isset($context[AbstractNormalizer::OBJECT_TO_POPULATE])) {
            // User doesn't have draft access: force publication date
            if (!$this->publishableHelper->isGranted()) {
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
            !$this->publishableHelper->isActivePublishedAt($object) ||
            !$this->publishableHelper->isGranted()
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
                $this->publishableHelper->isActivePublishedAt($object) &&
                new \DateTimeImmutable() >= $publicationDate
            ) {
                unset($data[$configuration->fieldName]);
            }
        }

        return $data;
    }

    private function unsetRestrictedData(array $data, Publishable $configuration): array
    {
        // It's not possible to change the publishedResource and draftResource properties
        unset($data[$configuration->associationName], $data[$configuration->reverseAssociationName]);

        // User doesn't have draft access: cannot set or change the publication date
        if (!$this->publishableHelper->isGranted()) {
            unset($data[$configuration->fieldName]);
        }

        return $data;
    }

    private function createDraft(object $object, Publishable $configuration, string $type): object
    {
        $em = $this->registry->getManagerForClass($type);
        if (!$em) {
            throw new InvalidArgumentException(sprintf('Could not find entity manager for class %s', $type));
        }

        /** @var ClassMetadataInfo $classMetadata */
        $classMetadata = $em->getClassMetadata($type);

        // Resource is a draft: nothing to do here anymore
        if (null !== $classMetadata->getFieldValue($object, $configuration->associationName)) {
            return $object;
        }

        $draft = clone $object; // Identifier(s) should be reset from AbstractComponent::__clone method

        // Empty publishedDate on draft
        $classMetadata->setFieldValue($draft, $configuration->fieldName, null);

        // Set publishedResource on draft
        $classMetadata->setFieldValue($draft, $configuration->associationName, $object);

        // Set draftResource on data
        $classMetadata->setFieldValue($object, $configuration->reverseAssociationName, $draft);

        // Add draft object to UnitOfWork
        $em->persist($draft);

        return $draft;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $this->publishableHelper->isConfigured($type);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
