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
use Silverback\ApiComponentsBundle\AttributeReader\RoutePrefixAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\OpenApi\OpenApiFactory;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class VersionedDocumentationNormalizer implements NormalizerInterface
{
    /** @var array<string, array<string, true|string>>|null */
    private ?array $supportedClassProperties = null;

    public function __construct(
        private readonly NormalizerInterface|DocumentationNormalizer $decorated,
        private readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly ExplicitAllowOnlyAttributeReader $explicitAllowOnlyReader,
        private readonly RoutePrefixAttributeReader $routePrefixReader,
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

        $scKey = isset($doc['supportedClass']) ? 'supportedClass' : (isset($doc['hydra:supportedClass']) ? 'hydra:supportedClass' : null);
        if (null !== $scKey && \is_array($doc[$scKey])) {
            $properties = $this->getSupportedClassProperties();
            foreach ($doc[$scKey] as &$supportedClass) {
                if (\is_array($supportedClass) && isset($supportedClass['title'], $properties[$supportedClass['title']])) {
                    $supportedClass = array_merge($supportedClass, $properties[$supportedClass['title']]);
                }
            }
            unset($supportedClass);
        }

        return $doc;
    }

    /**
     * @return array<string, array<string, true|string>>
     */
    private function getSupportedClassProperties(): array
    {
        if (null !== $this->supportedClassProperties) {
            return $this->supportedClassProperties;
        }

        $properties = [];
        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            $classProperties = [];
            if ($this->explicitAllowOnlyReader->isConfigured($resourceClass)) {
                $classProperties['explicitAllowOnly'] = true;
            }
            if (is_a($resourceClass, AbstractPageData::class, true) && $this->routePrefixReader->isConfigured($resourceClass)) {
                $classProperties['routePrefix'] = $this->routePrefixReader->getConfiguration($resourceClass)->prefix;
            }
            if ([] === $classProperties) {
                continue;
            }
            foreach ($this->resourceMetadataCollectionFactory->create($resourceClass) as $resourceMetadata) {
                $properties[$resourceMetadata->getShortName()] = $classProperties;
            }
        }

        return $this->supportedClassProperties = $properties;
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
