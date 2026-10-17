<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\HttpCache;

use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

final readonly class ScopedPurgeRequest
{
    public const string PATHS = 'paths';
    public const string TAGS = 'tags';
    public const string INVALID_BODY_MESSAGE = 'The body must be a JSON object with "paths", "tags" or both, or be empty to flush the whole cache.';
    public const string NOTHING_TO_PURGE_MESSAGE = 'Send "paths", "tags" or both.';
    public const string UNEXPECTED_FIELD_MESSAGE = 'This field was not expected.';
    public const string NOT_A_LIST_MESSAGE = 'This value should be a non-empty list of strings.';
    public const string NOT_A_STRING_MESSAGE = 'This value should be of type string.';
    public const string PATH_MESSAGE = 'A path must start with "/".';
    public const string EMPTY_TAG_MESSAGE = 'A cache tag cannot be empty.';
    public const string SPLIT_TAG_MESSAGE = 'A cache tag cannot contain a space or a comma.';

    /**
     * @param list<string> $paths
     * @param list<string> $tags
     */
    private function __construct(public array $paths, public array $tags)
    {
    }

    public static function fromContent(string $content): ?self
    {
        if ('' === $content) {
            return null;
        }

        try {
            $data = json_decode($content, false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw self::invalid([['', self::INVALID_BODY_MESSAGE]]);
        }

        if (!$data instanceof \stdClass) {
            throw self::invalid([['', self::INVALID_BODY_MESSAGE]]);
        }

        $fields = get_object_vars($data);
        $violations = [];
        foreach (array_keys($fields) as $field) {
            if (self::PATHS !== $field && self::TAGS !== $field) {
                $violations[] = [(string) $field, self::UNEXPECTED_FIELD_MESSAGE];
            }
        }
        if (!\array_key_exists(self::PATHS, $fields) && !\array_key_exists(self::TAGS, $fields)) {
            $violations[] = ['', self::NOTHING_TO_PURGE_MESSAGE];
        }

        $paths = self::strings($fields, self::PATHS, $violations, static fn (string $path): ?string => str_starts_with($path, '/') ? null : self::PATH_MESSAGE);
        $tags = self::strings($fields, self::TAGS, $violations, self::tagViolation(...));

        if ([] !== $violations) {
            throw self::invalid($violations);
        }

        return new self($paths, $tags);
    }

    public static function tagViolation(string $tag): ?string
    {
        if ('' === $tag) {
            return self::EMPTY_TAG_MESSAGE;
        }

        return preg_match('/[\s,]/', $tag) ? self::SPLIT_TAG_MESSAGE : null;
    }

    /**
     * @param callable(string): string $routeTag
     *
     * @return list<string>
     */
    public function resolveTags(callable $routeTag): array
    {
        $tags = $this->tags;
        $violations = [];
        foreach ($this->paths as $index => $path) {
            $tag = $routeTag($path);
            $message = self::tagViolation($tag);
            if (null !== $message) {
                $violations[] = [\sprintf('%s[%d]', self::PATHS, $index), $message];
                continue;
            }
            $tags[] = $tag;
        }

        if ([] !== $violations) {
            throw self::invalid($violations);
        }

        return array_values(array_unique($tags));
    }

    /**
     * @param array<array-key, mixed>         $fields
     * @param list<array{string, string}>     $violations
     * @param callable(string): (string|null) $check
     *
     * @return list<string>
     */
    private static function strings(array $fields, string $field, array &$violations, callable $check): array
    {
        if (!\array_key_exists($field, $fields)) {
            return [];
        }

        $values = $fields[$field];
        if (!\is_array($values) || [] === $values || !array_is_list($values)) {
            $violations[] = [$field, self::NOT_A_LIST_MESSAGE];

            return [];
        }

        $strings = [];
        foreach ($values as $index => $value) {
            $propertyPath = \sprintf('%s[%d]', $field, $index);
            if (!\is_string($value)) {
                $violations[] = [$propertyPath, self::NOT_A_STRING_MESSAGE];
                continue;
            }
            $message = $check($value);
            if (null !== $message) {
                $violations[] = [$propertyPath, $message];
                continue;
            }
            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * @param non-empty-list<array{string, string}> $violations
     */
    private static function invalid(array $violations): ValidationException
    {
        $list = new ConstraintViolationList();
        foreach ($violations as [$propertyPath, $message]) {
            $list->add(new ConstraintViolation($message, $message, [], null, $propertyPath, null));
        }

        return new ValidationException($list);
    }
}
