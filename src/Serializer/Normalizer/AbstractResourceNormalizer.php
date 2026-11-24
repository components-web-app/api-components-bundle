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

use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Utility\ApiResourceRouteFinder;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class AbstractResourceNormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'ABSTRACT_COMPONENT_NORMALIZER_ALREADY_CALLED';

    private ApiResourceRouteFinder $routeFinder;

    public function __construct(ApiResourceRouteFinder $routeFinder)
    {
        $this->routeFinder = $routeFinder;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        try {
            return !isset($context[self::ALREADY_CALLED]) && (new \ReflectionClass($type))->isAbstract();
        } catch (\ReflectionException $exception) {
            return false;
        }
    }

    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_CALLED] = true;

        if (\is_string($data)) {
            $iri = $data;
        } else {
            $iri = $data['@id'] ?? null;
        }
        if ($iri) {
            try {
                $routeParameters = $this->routeFinder->findByIri($iri);
            } catch (InvalidArgumentException $exception) {
                $message = \sprintf('Invalid IRI "%s".', $iri);
                if (!isset($context['not_normalizable_value_exceptions'])) {
                    throw new UnexpectedValueException($message, $exception->getCode(), $exception);
                }

                throw NotNormalizableValueException::createForUnexpectedDataType($message, $iri, [$type], $context['deserialization_path'] ?? null, true, $exception->getCode(), $exception);
            }
            $type = $routeParameters['_api_resource_class'];
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }
}
