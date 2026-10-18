<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Symfony\Component\HttpFoundation\Request;

trait ManifestTrait
{
    protected static function manifestUrlOf(AbstractPage $page): string
    {
        return '/_/resource_manifest/' . $page->getId();
    }

    /**
     * @param array<mixed>|null $manifest
     *
     * @return list<string>
     */
    protected static function manifestIrisAtDepth(int $depth, ?array $manifest = null): array
    {
        return self::flattenManifestNode(self::manifestDepthNode($depth, $manifest));
    }

    /**
     * @param array<mixed>|null $manifest
     */
    protected static function manifestRootIriAtDepth(int $depth, ?array $manifest = null): ?string
    {
        return self::manifestDepthNode($depth, $manifest)['iri'] ?? null;
    }

    /**
     * @param array<mixed>|null $manifest
     */
    protected static function assertManifestDepthContains(int $depth, string $iri, ?array $manifest = null): void
    {
        $iris = static::manifestIrisAtDepth($depth, $manifest);

        self::assertContains($iri, $iris, \sprintf('Manifest depth %d does not contain "%s". Has: %s', $depth, $iri, implode(', ', $iris)));
    }

    /**
     * @param array<mixed>|null $manifest
     */
    protected static function assertManifestDepthNotContains(int $depth, string $iri, ?array $manifest = null): void
    {
        self::assertNotContains($iri, static::manifestIrisAtDepth($depth, $manifest), \sprintf('Manifest depth %d contains "%s".', $depth, $iri));
    }

    /**
     * @param array<mixed>|null $manifest
     */
    protected static function assertManifestDepthContainsIriMatching(int $depth, string $pattern, ?array $manifest = null): void
    {
        $iris = static::manifestIrisAtDepth($depth, $manifest);

        self::assertNotEmpty(preg_grep($pattern, $iris), \sprintf('Manifest depth %d has no IRI matching %s. Has: %s', $depth, $pattern, implode(', ', $iris)));
    }

    /**
     * @param array<mixed>|null $manifest
     */
    protected static function assertManifestDepthContainsNoIriMatching(int $depth, string $pattern, ?array $manifest = null): void
    {
        $matching = preg_grep($pattern, static::manifestIrisAtDepth($depth, $manifest));

        self::assertEmpty($matching, \sprintf('Manifest depth %d contains %s matching %s.', $depth, implode(', ', $matching), $pattern));
    }

    /**
     * @param array<mixed>|null $manifest
     */
    protected static function assertEveryManifestIriResolves(?array $manifest = null): void
    {
        $manifest ??= json_decode((string) self::getClient()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $iris = [];
        foreach ($manifest['resource_iris'] ?? [] as $node) {
            array_push($iris, ...self::flattenManifestNode($node));
        }
        self::assertNotEmpty($iris, 'The manifest lists no IRI.');

        static::rebootKernel();
        $unresolved = [];
        foreach (array_unique($iris) as $iri) {
            $status = static::$kernel->handle(Request::create($iri, 'GET', server: ['HTTP_ACCEPT' => 'application/ld+json']))->getStatusCode();
            if (200 !== $status) {
                $unresolved[] = \sprintf('%s (%d)', $iri, $status);
            }
        }

        self::assertSame([], $unresolved, 'These manifest IRIs did not resolve anonymously.');
    }

    /**
     * @param array<mixed>|null $manifest
     *
     * @return array<mixed>
     */
    private static function manifestDepthNode(int $depth, ?array $manifest): array
    {
        $manifest ??= json_decode((string) self::getClient()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $node = $manifest['resource_iris'][$depth] ?? null;
        self::assertIsArray($node, \sprintf('The manifest has no depth %d.', $depth));

        return $node;
    }

    /**
     * @param array<mixed> $node
     *
     * @return list<string>
     */
    private static function flattenManifestNode(array $node): array
    {
        $iris = isset($node['iri']) ? [$node['iri']] : [];
        foreach ($node['children'] ?? [] as $child) {
            if (\is_array($child)) {
                array_push($iris, ...self::flattenManifestNode($child));
            }
        }

        return $iris;
    }
}
