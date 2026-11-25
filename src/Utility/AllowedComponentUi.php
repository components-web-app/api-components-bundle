<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Utility;

final class AllowedComponentUi
{
    /**
     * @param array<mixed>|null $allowedComponentUi
     */
    public static function allowsUiComponent(?array $allowedComponentUi, string $componentIri, ?string $uiComponent): bool
    {
        if (!isset($allowedComponentUi[$componentIri]['ui'])) {
            return true;
        }

        return \in_array($uiComponent, $allowedComponentUi[$componentIri]['ui'], true);
    }

    /**
     * @param array<mixed>|null $allowedComponentUi
     * @param array<mixed>|null $uiClassNames
     *
     * @return list<string>
     */
    public static function disallowedStyles(?array $allowedComponentUi, string $componentIri, ?array $uiClassNames): array
    {
        if (!isset($allowedComponentUi[$componentIri]['styles']) || null === $uiClassNames) {
            return [];
        }

        $disallowed = [];
        foreach ($uiClassNames as $style) {
            if (!\in_array($style, $allowedComponentUi[$componentIri]['styles'], true)) {
                $disallowed[] = \is_string($style) ? $style : json_encode($style, \JSON_THROW_ON_ERROR);
            }
        }

        return $disallowed;
    }

    public static function describeUiComponent(?string $uiComponent): string
    {
        return $uiComponent ?? 'null';
    }
}
