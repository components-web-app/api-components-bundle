<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Serializer\Normalizer;

use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteLiveResolver;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\RouteNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class RouteNormalizerTest extends TestCase
{
    public function test_a_redirecting_route_carries_its_final_path(): void
    {
        self::assertSame('/new', $this->normalize([])['redirectPath']);
    }

    public function test_a_redirecting_route_in_a_manifest_carries_no_redirect_path(): void
    {
        self::assertArrayNotHasKey('redirectPath', $this->normalize([CwaTagCollector::MANIFEST_CONTEXT_KEY => true]));
    }

    private function normalize(array $context): array
    {
        $target = $this->route('/new');
        $route = $this->route('/old')->setRedirect($target);

        $accessChecker = $this->createStub(ResourceAccessCheckerInterface::class);
        $accessChecker->method('isGranted')->willReturn(false);
        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturn(['@id' => '/_/routes//old']);

        $normalizer = new RouteNormalizer(new RouteLiveResolver(), $accessChecker, "is_granted('ROLE_ADMIN')");
        $normalizer->setNormalizer($inner);

        return $normalizer->normalize($route, 'jsonld', $context);
    }

    private function route(string $path): Route
    {
        $route = (new Route())->setPath($path)->setName($path);
        (new \ReflectionProperty(Route::class, 'id'))->setValue($route, Uuid::uuid4());

        return $route;
    }
}
