<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator\Code;

use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;

/**
 * @internal
 */
final class ImportCollector
{
    private const array CORE = [AbstractCwaScaffold::class, CwaFixtureBuilder::class];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, true> */
    private array $taken = [];

    public function __construct(string $className)
    {
        $this->taken[strtolower($className)] = true;
        foreach (self::CORE as $class) {
            $this->aliases[$class] = $this->shortName($class);
            $this->taken[strtolower($this->aliases[$class])] = true;
        }
    }

    public function add(string $class): string
    {
        $class = ltrim($class, '\\');
        if (isset($this->aliases[$class])) {
            return $this->aliases[$class];
        }

        $segments = explode('\\', $class);
        $alias = array_pop($segments);
        while (isset($this->taken[strtolower($alias)]) && [] !== $segments) {
            $alias = array_pop($segments) . $alias;
        }
        $base = $alias;
        for ($suffix = 2; isset($this->taken[strtolower($alias)]); ++$suffix) {
            $alias = $base . $suffix;
        }

        $this->taken[strtolower($alias)] = true;

        return $this->aliases[$class] = $alias;
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        $classes = array_keys($this->aliases);
        usort($classes, strcasecmp(...));

        return array_map(
            fn (string $class): string => $this->aliases[$class] === $this->shortName($class)
                ? \sprintf('use %s;', $class)
                : \sprintf('use %s as %s;', $class, $this->aliases[$class]),
            $classes,
        );
    }

    private function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return false === $position ? $class : substr($class, $position + 1);
    }
}
