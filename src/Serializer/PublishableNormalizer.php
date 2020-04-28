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

namespace Silverback\ApiComponentBundle\Serializer;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Publishable\PublishableHelper;
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

    public function __construct(PublishableHelper $publishableHelper, ManagerRegistry $registry, RequestStack $requestStack)
    {
        $this->publishableHelper = $publishableHelper;
        $this->registry = $registry;
        $this->requestStack = $requestStack;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;
        $data = $this->normalizer->normalize($object, $format, $context);
        $configuration = $this->publishableHelper->getConfiguration($object);
        if (!$configuration) {
            throw new InvalidArgumentException(sprintf('Could not get configuration for %s', \get_class($object)));
        }

        if (!\array_key_exists($configuration->publishedProperty, $data)) {
            $data[$configuration->publishedProperty] = $this->publishableHelper->isActivePublishedAt($object);
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) &&
            \is_object($data) &&
            !$data instanceof \Traversable &&
            $this->publishableHelper->isPublishable($data);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;
        $configuration = $this->publishableHelper->getConfiguration($type);
        if (!$configuration) {
            throw new InvalidArgumentException(sprintf('Could not get configuration for %s', $type));
        }

        // It's not possible to change the publishedResource and draftResource properties
        unset($data[$configuration->associationName], $data[$configuration->reverseAssociationName]);

        // User doesn't have draft access: cannot set or change the publication date
        if (!$this->publishableHelper->isGranted()) {
            unset($data[$configuration->fieldName]);
        }

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

        // No field has been updated: nothing to do here anymore
        if (empty($data)) {
            return $this->denormalizer->denormalize($data, $type, $format, $context);
        }

        $em = $this->registry->getManagerForClass($type);
        if (!$em) {
            throw new InvalidArgumentException(sprintf('Could not find entity manager for class %s', $type));
        }
        /** @var ClassMetadataInfo $classMetadata */
        $classMetadata = $em->getClassMetadata($type);

        // Resource is a draft: nothing to do here anymore
        // or User doesn't have draft access: update the original object
        if (
            null !== $classMetadata->getFieldValue($object, $configuration->associationName) ||
            !$this->publishableHelper->isActivePublishedAt($object) ||
            !$this->publishableHelper->isGranted()
        ) {
            return $this->denormalizer->denormalize($data, $type, $format, $context);
        }

        // Any field has been modified: create a draft
        $draft = clone $object; // Identifier(s) should be reset from AbstractComponent::__clone method

        // Add draft object to UnitOfWork
        $em->persist($draft);

        // Empty publishedDate on draft
        $classMetadata->setFieldValue($draft, $configuration->fieldName, null);

        // Set publishedResource on draft
        $classMetadata->setFieldValue($draft, $configuration->associationName, $object);

        // Set draftResource on data
        $classMetadata->setFieldValue($object, $configuration->reverseAssociationName, $draft);

        $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $draft;

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $this->publishableHelper->isPublishable($type);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
