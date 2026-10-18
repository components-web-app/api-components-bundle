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

use JsonSchema\SchemaStorage;
use JsonSchema\Uri\UriResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;

trait JsonTrait
{
    protected static function jsonSchemaDirectory(): string
    {
        return \dirname(__DIR__, 3) . '/features/assets/schema';
    }

    /**
     * @return array<mixed>
     */
    protected static function lastJson(): array
    {
        return json_decode(self::lastJsonContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<mixed>|null $json
     */
    protected static function jsonNode(string $path, ?array $json = null): mixed
    {
        $node = $json ?? static::lastJson();
        foreach (self::jsonPathSegments($path) as $segment) {
            if (!\is_array($node) || !\array_key_exists($segment, $node)) {
                self::fail(\sprintf('The JSON node "%s" does not exist in %s', $path, json_encode($json ?? static::lastJson())));
            }
            $node = $node[$segment];
        }

        return $node;
    }

    /**
     * @param array<mixed>|null $json
     */
    protected static function jsonNodeExists(string $path, ?array $json = null): bool
    {
        $node = $json ?? static::lastJson();
        foreach (self::jsonPathSegments($path) as $segment) {
            if (!\is_array($node) || !\array_key_exists($segment, $node)) {
                return false;
            }
            $node = $node[$segment];
        }

        return true;
    }

    /**
     * @param array<mixed>|null $json
     */
    protected static function assertJsonNodeSame(string $path, mixed $expected, ?array $json = null): void
    {
        self::assertSame($expected, static::jsonNode($path, $json), \sprintf('The JSON node "%s" is not the expected value.', $path));
    }

    /**
     * @param array<mixed>|null $json
     */
    protected static function assertJsonNodeExists(string $path, ?array $json = null): void
    {
        self::assertTrue(static::jsonNodeExists($path, $json), \sprintf('The JSON node "%s" does not exist.', $path));
    }

    /**
     * @param array<mixed>|null $json
     */
    protected static function assertJsonNodeNotExists(string $path, ?array $json = null): void
    {
        self::assertFalse(static::jsonNodeExists($path, $json), \sprintf('The JSON node "%s" exists.', $path));
    }

    /**
     * @param array<mixed>|null $json
     */
    protected static function assertJsonNodeCount(string $path, int $count, ?array $json = null): void
    {
        $node = static::jsonNode($path, $json);
        self::assertIsArray($node, \sprintf('The JSON node "%s" is not an array.', $path));
        self::assertCount($count, $node, \sprintf('The JSON node "%s" has %d elements.', $path, \count($node)));
    }

    /**
     * @param array<mixed>|null $json
     */
    protected static function assertJsonNodeMatches(string $path, string $pattern, ?array $json = null): void
    {
        $node = static::jsonNode($path, $json);
        self::assertIsString($node, \sprintf('The JSON node "%s" is not a string.', $path));
        self::assertMatchesRegularExpression($pattern, $node, \sprintf('The JSON node "%s" does not match %s.', $path, $pattern));
    }

    /**
     * @param list<mixed>       $expected
     * @param array<mixed>|null $json
     */
    protected static function assertJsonNodeListsExactly(string $path, array $expected, ?array $json = null): void
    {
        $actual = static::jsonNode($path, $json);
        self::assertIsArray($actual, \sprintf('The JSON node "%s" is not a list.', $path));
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual, \sprintf('The JSON node "%s" does not list exactly the expected values.', $path));
    }

    /**
     * @param array<mixed>|null $json
     */
    protected static function assertJsonNodeIsNow(string $path, \DateTimeInterface $now, ?array $json = null): void
    {
        $node = static::jsonNode($path, $json);
        self::assertIsString($node, \sprintf('The JSON node "%s" is not a date.', $path));
        $difference = abs($now->getTimestamp() - (new \DateTimeImmutable($node))->getTimestamp());
        self::assertLessThanOrEqual(1, $difference, \sprintf('The JSON node "%s" is %s, %d seconds from %s.', $path, $node, $difference, $now->format(\DATE_ATOM)));
    }

    protected static function assertJsonMatchesSchemaFile(string $file, ?string $content = null): void
    {
        self::assertValidAgainstSchemaFile(json_decode($content ?? self::lastJsonContent(), false, 512, \JSON_THROW_ON_ERROR), $file);
    }

    protected static function assertEachJsonItemMatchesSchemaFile(string $file, ?string $content = null): void
    {
        $items = json_decode($content ?? self::lastJsonContent(), false, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($items, 'The JSON is not an array.');
        foreach ($items as $item) {
            self::assertValidAgainstSchemaFile($item, $file);
        }
    }

    private static function assertValidAgainstSchemaFile(mixed $data, string $file): void
    {
        $path = static::jsonSchemaDirectory() . '/' . $file;
        self::assertFileExists($path);
        $schema = (new SchemaStorage(new UriRetriever(), new UriResolver()))->resolveRef('file://' . realpath($path));
        $validator = new Validator();
        $validator->check($data, $schema);

        self::assertTrue($validator->isValid(), \sprintf(
            "The JSON does not validate against %s:\n%s\n\n%s",
            $file,
            implode("\n", array_map(static fn (array $error): string => \sprintf('  - [%s] %s', $error['property'], $error['message']), $validator->getErrors())),
            json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
        ));
    }

    private static function lastJsonContent(): string
    {
        return (string) self::getClient()->getResponse()->getContent();
    }

    /**
     * @return list<string|int>
     */
    private static function jsonPathSegments(string $path): array
    {
        if ('root' === $path) {
            return [];
        }
        preg_match_all('/\[(\d+)\]|([^.\[\]]+)/', $path, $matches, \PREG_SET_ORDER);

        return array_map(static fn (array $match): string|int => '' !== $match[1] ? (int) $match[1] : $match[2], $matches);
    }
}
