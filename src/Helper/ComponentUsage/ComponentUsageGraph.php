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
 * @phpstan-type Holder array{group: string, pageDataProperty: ?string}
 * @phpstan-type Walk array{groups: list<string>, components: list<string>, pageDataProperties: list<string>}
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class ComponentUsageGraph
{
    /**
     * @param array<string, list<string>> $ownersByGroup
     * @param array<string, string>       $publishedByComponent
     * @param array<string, list<Holder>> $holdersByComponent
     */
    public function __construct(
        private array $ownersByGroup = [],
        private array $publishedByComponent = [],
        private array $holdersByComponent = [],
    ) {
    }

    /**
     * @param list<string> $overriddenProperties
     *
     * @return Walk
     */
    public function walkUpFrom(string $group, array $overriddenProperties = []): array
    {
        $groups = [];
        $components = [];
        $properties = [];
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
                    foreach ($this->holdersByComponent[$member] ?? [] as $holder) {
                        $property = $holder['pageDataProperty'];
                        if (null !== $property) {
                            $properties[$property] = $property;
                            if (\in_array($property, $overriddenProperties, true)) {
                                continue;
                            }
                        }
                        $queue[] = $holder['group'];
                    }
                }
            }
        }

        return ['groups' => array_values($groups), 'components' => array_values($components), 'pageDataProperties' => array_values($properties)];
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
