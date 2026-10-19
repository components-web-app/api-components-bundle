<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\ComponentUsage;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class ComponentUsageGraph
{
    /**
     * @param array<string, list<string>> $ownersByGroup
     * @param array<string, string>       $publishedByComponent
     * @param array<string, list<string>> $groupsByComponent
     */
    public function __construct(
        private array $ownersByGroup = [],
        private array $publishedByComponent = [],
        private array $groupsByComponent = [],
    ) {
    }

    /**
     * @return array{groups: list<string>, components: list<string>}
     */
    public function walkUpFrom(string $group): array
    {
        $groups = [];
        $components = [];
        $queue = [$group];

        while (null !== ($current = array_shift($queue))) {
            if (isset($groups[$current])) {
                continue;
            }
            $groups[$current] = $current;

            foreach ($this->ownersByGroup[$current] ?? [] as $owner) {
                foreach ($this->family($owner) as $member) {
                    if (isset($components[$member])) {
                        continue;
                    }
                    $components[$member] = $member;
                    array_push($queue, ...$this->groupsByComponent[$member] ?? []);
                }
            }
        }

        return ['groups' => array_values($groups), 'components' => array_values($components)];
    }

    /**
     * @return list<string>
     */
    private function family(string $component): array
    {
        $published = $this->publishedByComponent[$component] ?? null;

        return null === $published ? [$component] : [$component, $published];
    }
}
