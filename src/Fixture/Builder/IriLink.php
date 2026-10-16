<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Builder;

final readonly class IriLink
{
    public const string PAGE = 'page';
    public const string LAYOUT = 'layout';
    public const string PAGE_DATA = 'pageData';
    public const string ROUTE = 'route';

    /**
     * @param list<string|int> $key
     */
    private function __construct(
        public string $property,
        public array $key,
        public string $target,
        public string $name,
    ) {
    }

    /**
     * @param string|int|array<mixed>|null $key
     */
    public static function to(string $property, string|int|array|null $key = null, ?string $page = null, ?string $layout = null, ?string $pageData = null, ?string $route = null): self
    {
        $targets = array_filter([self::PAGE => $page, self::LAYOUT => $layout, self::PAGE_DATA => $pageData, self::ROUTE => $route], static fn (?string $name): bool => null !== $name);
        if (1 !== \count($targets)) {
            throw new \InvalidArgumentException(\sprintf('linkIri() for the property "%s" needs exactly one of page, layout, pageData or route; %d given.', $property, \count($targets)));
        }
        $key = \is_array($key) ? array_values($key) : (null === $key ? [] : [$key]);
        foreach ($key as $segment) {
            if (!\is_string($segment) && !\is_int($segment)) {
                throw new \InvalidArgumentException(\sprintf('linkIri() for the property "%s" takes a key path of strings and integers.', $property));
            }
        }

        return new self($property, $key, (string) array_key_first($targets), (string) reset($targets));
    }

    public function describe(): string
    {
        return $this->property . implode('', array_map(static fn (string|int $segment): string => '[' . json_encode($segment) . ']', $this->key));
    }
}
