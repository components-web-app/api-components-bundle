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

use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\Trait\ManifestDepthGroupTrait;
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
            // if a route has just been deleted which had other routes redirecting to it, then they will delete - but appear here as a redirect still without an ID for some reason
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

        if ($isRedirect) {
            // Use reflection to temporarily propagate page/pageData from the final route for serialization.
            // We must NOT call setPage/setPageData: those setters call $page->setRoute($this),
            // which corrupts Doctrine's identity map and causes stale data to be flushed to the DB.
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

        if ($isRedirect) {
            $normalized['redirectPath'] = $finalRoute->getPath();
            $reflPage->setValue($object, $originalPage);
            $reflPageData->setValue($object, $originalPageData);
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
}
