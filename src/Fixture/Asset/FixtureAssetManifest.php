<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Asset;

/**
 * @phpstan-type Entry array{name: string, mimeType: string, size: int, fields: list<string>}
 */
final class FixtureAssetManifest implements \Countable
{
    public const string HASH_PATTERN = '/^[0-9a-f]{64}$/';

    /** @var array<string, Entry> */
    private array $entries = [];

    public static function pathFor(string $directory, string $className): string
    {
        return $directory . '/' . $className . '.assets.json';
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException(\sprintf('The fixture asset manifest %s does not exist.', $path));
        }

        return self::fromJson((string) file_get_contents($path), $path);
    }

    public static function fromJson(string $json, string $source): self
    {
        $data = json_decode($json, true);
        $assets = \is_array($data) ? ($data['assets'] ?? null) : null;
        if (!\is_array($assets)) {
            throw self::invalid($source);
        }
        $manifest = new self();
        foreach ($assets as $hash => $entry) {
            if (!\is_string($hash) || 1 !== preg_match(self::HASH_PATTERN, $hash) || !self::isEntry($entry)) {
                throw self::invalid($source);
            }
            $manifest->entries[$hash] = ['name' => $entry['name'], 'mimeType' => $entry['mimeType'], 'size' => $entry['size'], 'fields' => $entry['fields']];
        }

        return $manifest;
    }

    public function add(string $hash, string $name, string $mimeType, int $size, string $field): bool
    {
        $entry = $this->entries[$hash] ?? ['name' => $name, 'mimeType' => $mimeType, 'size' => $size, 'fields' => []];
        if (!\in_array($field, $entry['fields'], true)) {
            $entry['fields'][] = $field;
            sort($entry['fields']);
        }
        $this->entries[$hash] = $entry;

        return $entry['name'] === $name;
    }

    /**
     * @return Entry|null
     */
    public function get(string $hash): ?array
    {
        return $this->entries[$hash] ?? null;
    }

    /**
     * @return list<string>
     */
    public function hashes(): array
    {
        $hashes = array_keys($this->entries);
        sort($hashes);

        return $hashes;
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    public function toJson(): string
    {
        $entries = $this->entries;
        ksort($entries);

        return json_encode(['assets' => (object) $entries], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @phpstan-assert-if-true Entry $entry
     */
    private static function isEntry(mixed $entry): bool
    {
        return \is_array($entry)
            && \is_string($entry['name'] ?? null)
            && \is_string($entry['mimeType'] ?? null)
            && \is_int($entry['size'] ?? null)
            && \is_array($entry['fields'] ?? null)
            && array_is_list($entry['fields'])
            && [] === array_filter($entry['fields'], static fn (mixed $field): bool => !\is_string($field));
    }

    private static function invalid(string $source): \RuntimeException
    {
        return new \RuntimeException(\sprintf('The fixture asset manifest %s is not valid. Generate it again with silverback:api-components:generate-fixtures.', $source));
    }
}
