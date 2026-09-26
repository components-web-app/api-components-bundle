<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedResource;

use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;

final readonly class OrphanedResourceReportChange
{
    public const array KINDS = ['componentGroups', 'componentPositions', 'components'];

    public function __construct(
        public OrphanedResourceReport $report,
        public ?OrphanedResourceReport $previous,
    ) {
    }

    public function hasChanged(): bool
    {
        foreach (self::KINDS as $kind) {
            if (self::normalise($this->report->{$kind}) !== self::normalise($this->previousIris($kind))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{componentGroups: list<string>, componentPositions: list<string>, components: list<string>}
     */
    public function getAdded(): array
    {
        $added = [];
        foreach (self::KINDS as $kind) {
            $added[$kind] = array_values(array_diff($this->report->{$kind}, $this->previousIris($kind)));
        }

        return $added;
    }

    public function getResolvedCount(): int
    {
        $resolved = 0;
        foreach (self::KINDS as $kind) {
            $resolved += \count(array_diff($this->previousIris($kind), $this->report->{$kind}));
        }

        return $resolved;
    }

    /**
     * @return array{componentGroups: int, componentPositions: int, components: int}
     */
    public function getCounts(): array
    {
        $counts = [];
        foreach (self::KINDS as $kind) {
            $counts[$kind] = \count($this->report->{$kind});
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    private function previousIris(string $kind): array
    {
        return null === $this->previous ? [] : $this->previous->{$kind};
    }

    /**
     * @param list<string> $iris
     *
     * @return list<string>
     */
    private static function normalise(array $iris): array
    {
        $iris = array_values(array_unique($iris));
        sort($iris);

        return $iris;
    }
}
