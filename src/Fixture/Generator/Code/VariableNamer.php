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

use Cocur\Slugify\Slugify;

final class VariableNamer
{
    public const int MAX_LENGTH = 40;
    public const int MAX_WORDS = 4;
    public const array IDENTIFYING_FIELDS = ['label', 'title', 'name', 'heading', 'reference'];

    private const array RESERVED_VARIABLES = ['this', 'cwa', 'page', 'layout', 'pageData'];
    private const array RESERVED_METHODS = ['build'];
    private const string DRAFT = 'Draft';
    private const array STOP_WORDS = ['a', 'an', 'and', 'at', 'by', 'for', 'in', 'is', 'my', 'of', 'on', 'or', 'our', 'the', 'to', 'with', 'your'];

    private readonly Slugify $slugify;

    /** @var array<string, true> */
    private array $variables = [];

    /** @var array<string, true> */
    private array $methods = [];

    public function __construct()
    {
        $this->slugify = new Slugify();
        $this->variables = array_fill_keys(self::RESERVED_VARIABLES, true);
        $this->methods = array_fill_keys(self::RESERVED_METHODS, true);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function identifyingValue(array $fields): ?string
    {
        foreach (self::IDENTIFYING_FIELDS as $field) {
            $value = $fields[$field] ?? null;
            if (\is_string($value) && '' !== trim($value)) {
                return $value;
            }
        }
        foreach ($fields as $value) {
            if (\is_string($value) && preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $value, $match)) {
                $heading = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]*>/', ' ', $match[1]), \ENT_QUOTES | \ENT_HTML5)));
                if ('' !== $heading) {
                    return $heading;
                }
            }
        }

        return null;
    }

    public function variable(string $shortClassName, string ...$phrases): string
    {
        return $this->unique($this->variables, $this->words($phrases), $this->classWords($shortClassName));
    }

    /**
     * @return array{string, string}
     */
    public function variableWithDraft(string $shortClassName, string ...$phrases): array
    {
        $words = $this->words($phrases);
        $classWords = $this->classWords($shortClassName);
        $published = $this->fit($words, $classWords, '', self::MAX_LENGTH - \strlen(self::DRAFT));
        for ($suffix = 2; isset($this->variables[$published]) || isset($this->variables[$published . self::DRAFT]); ++$suffix) {
            $published = $this->fit($words, $classWords, (string) $suffix, self::MAX_LENGTH - \strlen(self::DRAFT));
        }
        $this->variables[$published] = $this->variables[$published . self::DRAFT] = true;

        return [$published, $published . self::DRAFT];
    }

    public function method(string $kind, string ...$phrases): string
    {
        return $this->unique($this->methods, $this->words($phrases), $this->classWords($kind));
    }

    /**
     * @param array<string, true> $taken
     * @param list<string>        $words
     * @param list<string>        $classWords
     */
    private function unique(array &$taken, array $words, array $classWords, int $maxLength = self::MAX_LENGTH): string
    {
        $name = $this->fit($words, $classWords, '', $maxLength);
        for ($suffix = 2; isset($taken[$name]); ++$suffix) {
            $name = $this->fit($words, $classWords, (string) $suffix, $maxLength);
        }
        $taken[$name] = true;

        return $name;
    }

    /**
     * @param list<string> $words
     * @param list<string> $classWords
     */
    private function fit(array $words, array $classWords, string $suffix, int $maxLength): string
    {
        $name = $this->withSuffix($this->compose($words, $classWords), $suffix);
        while (\strlen($name) > $maxLength && [] !== $words) {
            $stopWords = array_keys(array_intersect($words, self::STOP_WORDS));
            if ([] !== $stopWords && \count($stopWords) < \count($words)) {
                unset($words[end($stopWords)]);
                $words = array_values($words);
            } else {
                array_pop($words);
            }
            $name = $this->withSuffix($this->compose($words, $classWords), $suffix);
        }
        while (\strlen($name) > $maxLength && \count($classWords) > 1) {
            array_shift($classWords);
            $name = $this->withSuffix($this->compose($words, $classWords), $suffix);
        }

        return $name;
    }

    private function withSuffix(string $name, string $suffix): string
    {
        return '' !== $suffix && ctype_digit(substr($name, -1)) ? $name . '_' . $suffix : $name . $suffix;
    }

    /**
     * @param list<string> $words
     * @param list<string> $classWords
     */
    private function compose(array $words, array $classWords): string
    {
        $leadingNumbers = [];
        while ([] !== $words && ctype_digit($words[0][0])) {
            $leadingNumbers[] = array_shift($words);
        }
        array_splice($words, [] === $words ? 0 : 1, 0, $leadingNumbers);
        $words = implode('', array_map(ucfirst(...), $words));
        if ('' === $words || ctype_digit($words[0])) {
            return strtolower($classWords[0]) . implode('', \array_slice($classWords, 1)) . $words;
        }

        return lcfirst($words) . implode('', $classWords);
    }

    /**
     * @param array<string> $phrases
     *
     * @return list<string>
     */
    private function words(array $phrases): array
    {
        $words = [];
        foreach ($phrases as $phrase) {
            foreach (explode('-', $this->slugify->slugify($phrase)) as $word) {
                if ('' !== $word && $word !== end($words)) {
                    $words[] = $word;
                }
            }
        }

        return \array_slice($words, 0, self::MAX_WORDS);
    }

    /**
     * @return list<string>
     */
    private function classWords(string $shortClassName): array
    {
        $words = preg_split('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ucfirst($shortClassName), -1, \PREG_SPLIT_NO_EMPTY);

        return false === $words || [] === $words ? ['Item'] : $words;
    }
}
