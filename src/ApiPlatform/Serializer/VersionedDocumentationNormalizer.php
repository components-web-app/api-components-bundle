<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\ApiPlatform\Serializer;

use ApiPlatform\Documentation\Documentation;
use ApiPlatform\Hydra\Serializer\DocumentationNormalizer;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Silverback\ApiComponentsBundle\AttributeReader\ExplicitAllowOnlyAttributeReader;
use Silverback\ApiComponentsBundle\OpenApi\OpenApiFactory;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class VersionedDocumentationNormalizer implements NormalizerInterface
{
    /** @var array<string, true>|null short names of resources carrying #[Silverback\ExplicitAllowOnly] */
    private ?array $explicitAllowOnlyTitles = null;

    public function __construct(
        private readonly NormalizerInterface|DocumentationNormalizer $decorated,
        private readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly ExplicitAllowOnlyAttributeReader $explicitAllowOnlyReader,
    ) {
    }

    /**
     * @param Documentation $object
     *
     * @throws ExceptionInterface
     */
    public function normalize($object, ?string $format = null, array $context = []): array
    {
        $doc = $this->decorated->normalize($object, $format, $context);
        if ('' !== $object->getVersion()) {
            $doc['info'] = ['version' => OpenApiFactory::getExtendedVersion($object->getVersion())];
        }

        // Surface the per-type `explicitAllowOnly` flag on each flagged component's supportedClass
        // entry so the front-end (which reads the Hydra API docs, not per-instance _metadata) can
        // hide/reject opt-in-only component types. Matched by `title` (the class short name).
        $scKey = isset($doc['supportedClass']) ? 'supportedClass' : (isset($doc['hydra:supportedClass']) ? 'hydra:supportedClass' : null);
        if (null !== $scKey && \is_array($doc[$scKey])) {
            $flagged = $this->getExplicitAllowOnlyTitles();
            foreach ($doc[$scKey] as &$supportedClass) {
                if (\is_array($supportedClass) && isset($supportedClass['title'], $flagged[$supportedClass['title']])) {
                    $supportedClass['explicitAllowOnly'] = true;
                }
            }
            unset($supportedClass);
        }

        return $doc;
    }

    /**
     * @return array<string, true>
     */
    private function getExplicitAllowOnlyTitles(): array
    {
        if (null !== $this->explicitAllowOnlyTitles) {
            return $this->explicitAllowOnlyTitles;
        }

        $titles = [];
        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            if (!$this->explicitAllowOnlyReader->isConfigured($resourceClass)) {
                continue;
            }
            foreach ($this->resourceMetadataCollectionFactory->create($resourceClass) as $resourceMetadata) {
                $titles[$resourceMetadata->getShortName()] = true;
            }
        }

        return $this->explicitAllowOnlyTitles = $titles;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }
}
