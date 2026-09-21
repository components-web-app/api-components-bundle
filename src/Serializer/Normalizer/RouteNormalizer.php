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

use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteLiveResolver;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\Trait\ManifestDepthGroupTrait;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use ManifestDepthGroupTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'ROUTE_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly RouteLiveResolver $routeLiveResolver,
        private readonly ResourceAccessCheckerInterface $resourceAccessChecker,
        private readonly string $publicationPermission,
        private readonly ?ResourceMetadataProvider $resourceMetadataProvider = null,
    ) {
    }

    /**
     * @param Route      $object
     * @param mixed|null $format
     */
    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        $context[self::ALREADY_CALLED] = true;

        $finalRoute = $object;

        $redirectedRoutes = [$finalRoute->getId()];
        while ($nextRedirect = $finalRoute->getRedirect()) {
            if (!$nextRedirect->getId()) {
                break;
            }
            if (\in_array($nextRedirect->getId(), $redirectedRoutes, true)) {
                throw new CircularReferenceException(\sprintf('The redirect routes result in a circular reference: %s', implode(' -> ', $redirectedRoutes)));
            }
            $redirectedRoutes[] = $nextRedirect->getId();
            $finalRoute = $nextRedirect;
        }

        $isRedirect = $finalRoute !== $object;
        $mayReadUnpublished = $this->mayReadUnpublished();

        if ($mayReadUnpublished && $this->resourceMetadataProvider) {
            $this->resourceMetadataProvider
                ->findResourceMetadata($object)
                ->setEffectiveLiveAt($this->routeLiveResolver->resolveEffectiveLiveAt($object)?->format(\DateTimeInterface::ATOM));
        }
        $propagateTarget = $isRedirect && ($mayReadUnpublished || $this->routeLiveResolver->isLive($finalRoute));

        if ($propagateTarget) {
            $reflPage = new \ReflectionProperty($object, 'page');
            $reflPageData = new \ReflectionProperty($object, 'pageData');
            $originalPage = $reflPage->getValue($object);
            $originalPageData = $reflPageData->getValue($object);
            if (null === $originalPage) {
                $reflPage->setValue($object, $finalRoute->getPage());
            }
            if (null === $originalPageData) {
                $reflPageData->setValue($object, $finalRoute->getPageData());
            }
        }

        $normalized = $this->normalizer->normalize($object, $format, $context);

        if ($propagateTarget) {
            $reflPage->setValue($object, $originalPage);
            $reflPageData->setValue($object, $originalPageData);
        }

        if ($isRedirect && \is_array($normalized)) {
            $normalized['redirectPath'] = $finalRoute->getPath();
        }

        if (!$mayReadUnpublished && \is_array($normalized) && \array_key_exists('redirectedFrom', $normalized)) {
            $nonLivePaths = [];
            $visited = [];
            $this->collectNonLivePaths($object, $nonLivePaths, $visited);
            $normalized = $this->pruneRedirectedFrom($normalized, $nonLivePaths);
        }

        return $normalized;
    }

    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $data instanceof Route;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Route::class => false];
    }

    private function mayReadUnpublished(): bool
    {
        return $this->resourceAccessChecker->isGranted(Route::class, $this->publicationPermission);
    }

    /**
     * @param array<string, true> $paths
     * @param array<string, true> $visited
     */
    private function collectNonLivePaths(Route $route, array &$paths, array &$visited): void
    {
        foreach ($route->getRedirectedFrom() as $redirectedFrom) {
            $id = $redirectedFrom->getId()?->toString();
            if (null !== $id) {
                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;
            }
            if (!$this->routeLiveResolver->isLive($redirectedFrom)) {
                $paths[$redirectedFrom->getPath()] = true;
            }
            $this->collectNonLivePaths($redirectedFrom, $paths, $visited);
        }
    }

    /**
     * @param array<string, true> $nonLivePaths
     */
    private function pruneRedirectedFrom(array $node, array $nonLivePaths): array
    {
        if (!isset($node['redirectedFrom']) || !\is_array($node['redirectedFrom'])) {
            return $node;
        }

        $kept = [];
        foreach ($node['redirectedFrom'] as $child) {
            if (!\is_array($child)) {
                $kept[] = $child;
                continue;
            }
            if (isset($child['path']) && isset($nonLivePaths[$child['path']])) {
                continue;
            }
            $kept[] = $this->pruneRedirectedFrom($child, $nonLivePaths);
        }
        $node['redirectedFrom'] = $kept;

        return $node;
    }
}
