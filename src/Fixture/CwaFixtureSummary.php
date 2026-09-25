<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture;

final class CwaFixtureSummary implements \Stringable
{
    public const string CREATED = 'created';
    public const string KEPT = 'kept';
    public const string SKIPPED = 'skipped';

    public const string PAGE = 'page';
    public const string PAGE_DATA = 'page data';
    public const string LAYOUT = 'layout';
    public const string ROUTE = 'route';
    public const string GROUP = 'group';
    public const string COMPONENT = 'component';
    public const string ENTITY = 'entity';

    private const array TYPES = [self::PAGE, self::PAGE_DATA, self::LAYOUT, self::ROUTE, self::GROUP, self::COMPONENT, self::ENTITY];

    private const array PLURALS = [
        self::PAGE => 'pages',
        self::PAGE_DATA => 'page data',
        self::LAYOUT => 'layouts',
        self::ROUTE => 'routes',
        self::GROUP => 'groups',
        self::COMPONENT => 'components',
        self::ENTITY => 'entities',
    ];

    /** @var array<string, array<string, array<string, int>>> */
    private array $counts = [self::CREATED => [], self::KEPT => [], self::SKIPPED => []];

    public function record(string $outcome, string $type, string $reason = ''): void
    {
        $this->counts[$outcome][$type][$reason] = ($this->counts[$outcome][$type][$reason] ?? 0) + 1;
    }

    public function count(string $outcome, string $type, ?string $reason = null): int
    {
        $byReason = $this->counts[$outcome][$type] ?? [];

        return null === $reason ? array_sum($byReason) : ($byReason[$reason] ?? 0);
    }

    public function __toString(): string
    {
        $parts = [];
        foreach ($this->counts as $outcome => $types) {
            $items = [];
            foreach (self::TYPES as $type) {
                $reasons = $types[$type] ?? [];
                ksort($reasons);
                foreach ($reasons as $reason => $count) {
                    $items[] = \sprintf('%d %s%s', $count, 1 === $count ? $type : self::PLURALS[$type], '' === $reason ? '' : ' (' . $reason . ')');
                }
            }
            if ([] !== $items) {
                $parts[] = $outcome . ' ' . implode(', ', $items);
            }
        }

        return 'CWA scaffold: ' . ([] === $parts ? 'nothing to load' : implode('; ', $parts));
    }
}
