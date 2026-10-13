<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\HttpCache\Warm;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ModuleRequestHeaders
{
    public const string ACCEPT = 'application/ld+json,application/json';
    public const string ACCEPT_ENCODING = 'gzip';

    private const string NEXT_COMPONENT = '/componentGroups/*/componentPositions/*/component';

    private const array TYPE_IRI_PREFIXES = [
        'route' => '/_/routes/',
        'page' => '/_/pages/',
        'layout' => '/_/layouts/',
        'page_data' => '/page_data/',
        'component_group' => '/_/component_groups/',
        'component_position' => '/_/component_positions/',
        'component' => '/component/',
    ];

    private const array PRELOADS = [
        'route' => [
            '/page/layout' . self::NEXT_COMPONENT . self::NEXT_COMPONENT,
            '/page' . self::NEXT_COMPONENT . self::NEXT_COMPONENT,
            '/pageData/page/layout' . self::NEXT_COMPONENT . self::NEXT_COMPONENT,
            '/pageData/page' . self::NEXT_COMPONENT . self::NEXT_COMPONENT,
        ],
        'page' => [
            '/layout' . self::NEXT_COMPONENT . self::NEXT_COMPONENT,
            self::NEXT_COMPONENT . self::NEXT_COMPONENT,
        ],
        'layout' => [
            self::NEXT_COMPONENT . self::NEXT_COMPONENT,
        ],
        'page_data' => [
            '/page/layout' . self::NEXT_COMPONENT . self::NEXT_COMPONENT,
            '/page' . self::NEXT_COMPONENT . self::NEXT_COMPONENT,
        ],
        'component_group' => [
            '/componentPositions/*/component' . self::NEXT_COMPONENT . self::NEXT_COMPONENT,
        ],
        'component_position' => [
            '/component' . self::NEXT_COMPONENT . self::NEXT_COMPONENT,
        ],
        'component' => [
            self::NEXT_COMPONENT,
        ],
    ];

    public static function preload(string $iri, string $apiPrefix): ?string
    {
        $type = self::type($iri, $apiPrefix);

        return null === $type ? null : implode(',', self::PRELOADS[$type]);
    }

    public static function isPageData(string $iri, string $apiPrefix): bool
    {
        return 'page_data' === self::type($iri, $apiPrefix);
    }

    public static function routeIriPrefix(string $apiPrefix): string
    {
        return $apiPrefix . self::TYPE_IRI_PREFIXES['route'];
    }

    public static function manifestIri(string $apiPrefix, string $path): string
    {
        return $apiPrefix . '/_/resource_manifest/' . $path;
    }

    private static function type(string $iri, string $apiPrefix): ?string
    {
        if ('' !== $apiPrefix && str_starts_with($iri, $apiPrefix)) {
            $iri = substr($iri, \strlen($apiPrefix));
        }
        foreach (self::TYPE_IRI_PREFIXES as $type => $prefix) {
            if (str_starts_with($iri, $prefix) || $iri === substr($prefix, 0, -1)) {
                return $type;
            }
        }

        return null;
    }
}
