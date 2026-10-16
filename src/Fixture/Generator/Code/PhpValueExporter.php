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

/**
 * @internal
 */
final class PhpValueExporter
{
    public const int SPLIT_LENGTH = 100;

    private const string CONTINUATION = '    ';
    private const string BLOCK_TAG = '<(\/?)(?:p|h[1-6]|ul|ol|li|div|section|article|blockquote|table|tr|figure|pre)(?=[\s>\/])[^>]*>';
    private const string NOWDOC_MARKER = 'HTML';

    public function export(mixed $value, ?ImportCollector $imports = null): ?string
    {
        if (null === $value || \is_bool($value)) {
            return strtolower(var_export($value, true));
        }
        if (\is_int($value) || \is_float($value)) {
            return var_export($value, true);
        }
        if (\is_string($value)) {
            return $this->exportString($value);
        }
        if ($value instanceof \DateTimeInterface) {
            return $this->exportDate($value);
        }
        if ($value instanceof \UnitEnum) {
            $class = null === $imports ? '\\' . $value::class : $imports->add($value::class);

            return $class . '::' . $value->name;
        }
        if (\is_array($value)) {
            return $this->exportArray($value, $imports);
        }

        return null;
    }

    private function exportDate(\DateTimeInterface $date): ?string
    {
        $class = $date::class;
        if (\DateTimeImmutable::class !== $class && \DateTime::class !== $class) {
            return null;
        }
        $format = '000000' === $date->format('u') ? 'Y-m-d\TH:i:sP' : 'Y-m-d\TH:i:s.uP';

        return \sprintf("new \\%s('%s')", $class, $date->format($format));
    }

    /**
     * @param array<mixed> $value
     */
    private function exportArray(array $value, ?ImportCollector $imports): ?string
    {
        $items = [];
        foreach ($value as $key => $item) {
            $exported = $this->export($item, $imports);
            if (null === $exported) {
                return null;
            }
            $items[] = array_is_list($value) ? $exported : var_export($key, true) . ' => ' . $exported;
        }

        return '[' . implode(', ', $items) . ']';
    }

    private function exportString(string $value): string
    {
        if (\strlen($value) < self::SPLIT_LENGTH) {
            return $this->quote($value);
        }
        if (str_contains($value, "\n") && null !== ($nowdoc = $this->nowdoc($value))) {
            return $nowdoc;
        }

        return implode("\n" . self::CONTINUATION . '. ', array_map($this->quote(...), $this->splitAtBlocks($value)));
    }

    private function quote(string $value): string
    {
        if (1 !== preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value)) {
            return var_export($value, true);
        }

        $escaped = preg_replace_callback(
            '/[\x00-\x1F\x7F"\\\\$]/',
            static fn (array $match): string => match ($match[0]) {
                "\n" => '\n',
                "\r" => '\r',
                "\t" => '\t',
                '"', '\\', '$' => '\\' . $match[0],
                default => \sprintf('\x%02X', \ord($match[0])),
            },
            $value,
        );

        return '"' . $escaped . '"';
    }

    private function nowdoc(string $value): ?string
    {
        if (1 === preg_match('/[\x00-\x08\x0B-\x1F\x7F]|^\t|[ \t]$/m', $value)) {
            return null;
        }

        $marker = self::NOWDOC_MARKER;
        for ($suffix = 2; 1 === preg_match('/^[ \t]*' . $marker . '(?![A-Za-z0-9_\x80-\xff])/m', $value); ++$suffix) {
            $marker = self::NOWDOC_MARKER . $suffix;
        }

        $lines = array_map(
            static fn (string $line): string => '' === $line ? '' : self::CONTINUATION . $line,
            explode("\n", $value),
        );

        return "<<<'" . $marker . "'\n" . implode("\n", $lines) . "\n" . self::CONTINUATION . $marker;
    }

    /**
     * @return list<string>
     */
    private function splitAtBlocks(string $value): array
    {
        preg_match_all('/' . self::BLOCK_TAG . '/i', $value, $tags, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);

        $pieces = [];
        $start = 0;
        $depth = 0;
        foreach ($tags as $tag) {
            if ('' === $tag[1][0]) {
                ++$depth;
                continue;
            }
            $depth = max(0, $depth - 1);
            if (0 === $depth) {
                $end = $tag[0][1] + \strlen($tag[0][0]);
                $pieces[] = substr($value, $start, $end - $start);
                $start = $end;
            }
        }
        if ($start < \strlen($value)) {
            $pieces[] = substr($value, $start);
        }

        return $pieces;
    }
}
