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

use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\Page\TemplatePageDataCounter;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class TemplatePageNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'TEMPLATE_PAGE_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly TemplatePageDataCounter $counter,
        private readonly PublishableStatusChecker $publishableStatusChecker,
        private readonly ResourceMetadataProvider $resourceMetadataProvider,
    ) {
    }

    /**
     * @param Page $object
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;

        if ($object->isTemplate && !($context[CwaTagCollector::MANIFEST_CONTEXT_KEY] ?? false) && $this->publishableStatusChecker->isGranted(Page::class)) {
            $this->resourceMetadataProvider->findResourceMetadata($object)->setPageDataCount($this->counter->count($object));
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $data instanceof Page;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Page::class => false];
    }
}
