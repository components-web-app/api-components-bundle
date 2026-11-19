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

use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentsBundle\Utility\RouteName;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class EmbeddedRouteDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const string ALREADY_CALLED = 'EMBEDDED_ROUTE_DENORMALIZER_ALREADY_CALLED';

    public function __construct(private readonly RouteRepository $routeRepository)
    {
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && \is_array($data) && is_a($type, AbstractPage::class, true);
    }

    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_CALLED] = true;

        if (\is_array($data['route'] ?? null)) {
            if (null === ($data['route']['name'] ?? null) && \is_string($data['route']['path'] ?? null)) {
                $data['route']['name'] = $this->uniqueName(RouteName::fromPath($data['route']['path']));
            }
            if (\is_array($context['groups'] ?? null) && [] !== $context['groups']) {
                $context['groups'][] = Route::EMBEDDED_WRITE_GROUP;
            }
        }

        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        $route = $object instanceof AbstractPage ? $object->getRoute() : null;
        if (null !== $route && null === $route->getPage() && null === $route->getPageData()) {
            if ($object instanceof Page) {
                $route->setPage($object);
            } elseif ($object instanceof AbstractPageData) {
                $route->setPageData($object);
            }
        }

        return $object;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }

    private function uniqueName(string $name): string
    {
        $candidate = $name;
        for ($suffix = 1; $this->routeRepository->count(['name' => $candidate]) > 0; ++$suffix) {
            $candidate = RouteName::withSuffix($name, $suffix);
        }

        return $candidate;
    }
}
